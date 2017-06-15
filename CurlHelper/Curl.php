<?php
/** Curl Wrapper (command or lib)
 */

class CurlHelper {

	public static $HttpInfo = 0;
	public static $Error;
	public static $Errno;
	protected static $lastOptions;
	protected static $originOptions;
	protected static $logHandler;

	public static $SlowTime = 1;//second

	/** 参数映射(将常量名转为易懂的参数名) */
	private static $Options = array(
		/** 基本参数 */
		'url'             => 'CURLOPT_URL',
		'method'          => 'CURLOPT_CUSTOMREQUEST',
		'data'            => 'CURLOPT_POSTFIELDS', // array or string , file begin with '@'
		'ua'              => 'CURLOPT_USERAGENT',
		'timeout'         => 'CURLOPT_TIMEOUT', // (secs) 0 means indefinitely
		'connect_timeout' => 'CURLOPT_CONNECTTIMEOUT', //
		'referer'         => 'CURLOPT_REFERER',
		'binary'          => 'CURLOPT_BINARYTRANSFER',
		'port'            => 'CURLOPT_PORT',
		/** 请求头信息 */
		'header'  => 'CURLOPT_HEADER', // TRUE:include header;
		'headers' => 'CURLOPT_HTTPHEADER', // array
		/** 文件上传/下载 */
		'download' => 'CURLOPT_FILE', // writing file stream (using fopen()), default is STDOUT
		'upload'   => 'CURLOPT_INFILE', // reading file stream
		/** other */
		'transfer' => 'CURLOPT_RETURNTRANSFER', // TRUE:return string; FALSE:output directly (curl_exec)
		/** follow the redirect url (HTTP 301 302)*/
		'follow_location' => 'CURLOPT_FOLLOWLOCATION',
		/** ssl verifypeer */
		'ssl_verifypeer' => 'CURLOPT_SSL_VERIFYPEER',
		'timeout_ms'     => 'CURLOPT_TIMEOUT_MS', // milliseconds,  libcurl version > 7.36.0 ,

	);

	public static function lastOptions() {
		return self::$lastOptions;
	}
	public static function originOptions() {
		return self::$originOptions;
	}
	/** 通用Curl处理过程 */
	protected static function _Process($url, $options = array()) {
		// 参数检查
		if (empty($url)) {
			self::$Error = 'url cannot be empty';
			self::$Errno = -100;
			return FALSE;
		}
		// 设置选项默认值
		$options = array_merge(array('header' => 0, 'transfer' => 1, 'url' => $url, 'follow_location' => 1, 'timeout' => 10), $options);
		// 优化IP参数 (和传统使用不同，url使用域名，另外提供ip即可)
		if (!empty($options['ip'])) {
			// 提取主机名，加入头信息
			preg_match('/\/\/([^\/]+)/', $options['url'], $matches);
			$host = $matches[1];
			if (empty($options['headers']) || !is_array($options['headers'])) {
				$options['headers'] = array('Host: '.$host);
			} else {
				$options['headers'][] = 'Host: '.$host;
			}
			// 使用IP地址修改链接地址
			$options['url'] = preg_replace('/\/\/([^\/]+)/', '//'.$options['ip'], $options['url']);
			unset($options['ip']);
			unset($host);

		}
		// 优化HTTP协议版本参数
		if (!empty($options['http_version'])) {
			$version                                                       = $options['http_version'];
			if ($version == '1.0') {$options['CURLOPT_HTTP_VERSION']       = CURLOPT_HTTP_VERSION_1_0;
			} elseif ($version == '1.1') {$options['CURLOPT_HTTP_VERSION'] = CURLOPT_HTTP_VERSION_1_1;
			}

			unset($version);
		}
		// 优化info流程
		if (isset($options['return_info'])) {
			$return_info = $options['return_info'];
			unset($options['return_info']);

		} else {
			$return_info = FALSE;
		}
		self::$originOptions = $options;
		// 映射参数(将自定义参数映射为系统CURLOPT_参数)，并保留至调试使用
		foreach ($options as $key => $val) {
			if (isset(self::$Options[$key])) {
				$options[constant(self::$Options[$key])] = $val;
			} elseif ((strpos($key, 'CURLOPT_') === 0) && defined($key)) {
				$options[constant($key)] = $val;
			}
			unset($options[$key]);
		}

		if (!isset($options[CURLOPT_TIMEOUT_MS])) {//支持timeout小数秒
			$options[CURLOPT_TIMEOUT_MS] = $options[CURLOPT_TIMEOUT]*1000;
		} else {
			$options[CURLOPT_TIMEOUT_MS] = intval($options[CURLOPT_TIMEOUT_MS]);
		}

		ksort($options, SORT_NUMERIC);//timeout_ms需要在timeout后设置才生效
		self::$lastOptions = $options;
		// 执行操作
		$curl = curl_init();
		curl_setopt_array($curl, $options);
		$result         = curl_exec($curl);
		$httpInfo       = curl_getinfo($curl);
		self::$HttpInfo = $httpInfo;
		// 输出日志
		if ($result === FALSE || $httpInfo['http_code'] != 200) {
			self::$Error = $msg = curl_error($curl).' ('.curl_errno($curl).')';
			self::$Errno = curl_errno($curl);
			self::_Logger($url, $msg, $options, $result, $httpInfo);
		} else {
			if (isset($httpInfo['total_time']) && $httpInfo['total_time'] > self::$SlowTime) {
				self::_Logger($url, 'slow time,take '.$httpInfo['total_time'], $options, $result, $httpInfo);
			}
			// 返回信息
			if ($return_info) {
				$result = $httpInfo;
			}
		}

		// 关闭对象
		curl_close($curl);
		return $result;
	}

	/** 获取头信息(暂时只支持GET，返回解析后的数组) */
	public static function Header($url, $options = array()) {
		$options = array_merge(array('header' => 1, 'CURLOPT_NOBODY' => TRUE), $options);
		$header  = self::_Process($url, $options);
		if (!$header) {return $header;
		}

		$header = trim($header);
		$header = explode("\n", $header);
		$result = array();
		foreach ($header as $h) {
			if (strpos($h, ':')) {list($k, $v) = explode(':', $h);
			} else {
				list($k, $v) = array(0, $h);
			}

			$result[$k] = trim($v);
		}
		return $result;
	}

	/** HTTP Get请求 */
	public static function Get($url, $data = array(), $options = array()) {
		if (!empty($data)) {
			$url .= (strpos($url, '?') === FALSE)?'?':'&';
			$url .= http_build_query($data);
		}
		$opts = array_merge($options, array('url' => $url, 'method' => 'GET'));
		return self::_Process($url, $opts);
	}

	/** HTTP Post */
	public static function Post($url, $data = array(), $options = array()) {
		$opts = array_merge($options, array('url' => $url, 'method' => 'POST', 'data' => $data));
		return self::_Process($url, $opts);
	}

	/** (Multi) Download binary file
	 *  @param  string|array    $url_or_urls
	 *  @param  string  $file_or_dir
	 *  @param  array   $options (no yet use)
	 *  @return FALSE for failed / 1 for success
	 */
	public static function Download($url_or_urls, $file_or_dir, $options = array()) {
		//
		if (is_array($url_or_urls) && is_array($file_or_dir) && count($url_or_urls) != count($file_or_dir)) {return FALSE;
		}

		// valid parameters
		$urls  = is_string($url_or_urls)?array($url_or_urls):$url_or_urls;
		$files = array();
		if (is_string($file_or_dir)) {
			$dir = is_dir($file_or_dir);
			foreach ($urls as $url) {
				$files[] = $dir?$dir.basename($url):$file_or_dir;
			}
		} else {
			$files = $file_or_dir;
		}
		// multi download
		$result = 0;
		for ($i = 0, $len = count($urls); $i < $len; $i++) {
			$url     = $urls[$i];
			$fp      = fopen($files[$i], 'w+');
			$options = array('binary' => 1, 'download' => $fp);
			$r       = self::_Process($url, $options);
			if ($r !== FALSE) {$result++;
			}

			fclose($fp);
		}
		return $result;
	}

	/** 速度测试
	 *
	 *  请求指定地址$url，返回时间信息
	 *
	 */
	public static function Info($url, $options = array()) {
		$options = array_merge(array('return_info' => TRUE), $options);
		$info    = self::_Process($url, $options);
		if (!$info) {return $info;
		}

		return array(
			'http_status'        => $info['http_code'],
			'total_time'         => $info['total_time'],
			'connect_time'       => $info['connect_time'],
			'namelookup_time'    => $info['namelookup_time'],
			'pretransfer_time'   => $info['pretransfer_time'],
			'starttransfer_time' => $info['starttransfer_time'],

			'upload_size'    => $info['size_upload'],
			'download_size'  => $info['size_download'],
			'upload_speed'   => $info['speed_upload'],
			'download_speed' => $info['speed_download'],

		);
	}

	public static function setLogHandler(callback $handler) {
		self::$logHandler = $handler;
	}

	/** 日志记录 */
	private static function _Logger($url, $msg, $params, $response, $httpInfo) {
		self::$logHandler($url, $msg, $params, $response, $httpInfo);
	}
}
