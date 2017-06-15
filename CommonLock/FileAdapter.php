<?php
require_once __DIR__.'/LockerInterface.php';
class CLock_FileAdapter implements CLock_LockerInterface {
	/**
	 * @see CLock_LockerInterface::lock()
	 */
	public function lock($resource) {
        $tryCount = 50;
        while($tryCount>0) {
            if (!flock($resource, LOCK_EX | LOCK_NB)) {
                usleep(200000);
                $tryCount--;
            }
            else {
                break;
            }
        }
		//flock($resource, LOCK_EX);
	}
	/**
	 * @see CLock_LockerInterface::unlock()
	 */
	public function unlock($resource) {
		 flock($resource, LOCK_UN);
	}
	/**
	 * @see CLock_LockerInterface::getResource()
	 */
	public function getResource($namespace, $name, $limit = 0) {
		if (!self::$_lockFileDirectory) {
			throw new RuntimeException('lock file directory is required');
		}
		$id = crc32($namespace.':'.$name);
		if ($limit) {
			$id = $id % $limit;
		}
		$file = self::$_lockFileDirectory.'/'.$id.'.lock';
		$result = @fopen($file, 'w');
		if ($result === false) {
			throw new RuntimeException('fail create lock file resource:'.$file);
		}
		return $result;
	}
	/**
	 * @var string
	 */
	protected static $_lockFileDirectory;
	/**
	 * @param string $path
	 */
	public static function setLockFileDirectory($path) {
		self::$_lockFileDirectory = $path;
	}
}
CLock_FileAdapter::setLockFileDirectory(sys_get_temp_dir());
