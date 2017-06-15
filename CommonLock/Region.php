<?php
/**
 * @author zhaoqing
 */
class CLock_Region {
	/**
	 * @param string $namespace
	 * @param string $name
	 * @param int $limit
	 * @return CLock_Region
	 */
	public static function create($namespace, $name, $limit = 0) {
		require_once __DIR__.'/FileAdapter.php';
		$locker = new CLock_FileAdapter();
		$resource = $locker->getResource($namespace, $name, $limit);
		return new self($resource, $locker);
	}
	/**
	 * @var CLock_LockerInterface
	 */
	protected $_locker;
	/**
	 * @var mix
	 */
	protected $_resource;
	/**
	 * @param string $resource
	 * @param CLock_LockerInterface $locker
	 */
	protected function __construct($resource, $locker) {
		$this->_locker = $locker;
		$this->_resource = $resource;
	}
	/**
	 * @return void
	 */
	public function lock() {
		$this->_locker->lock($this->_resource);
	}
	/**
	 * @return void
	 */
	public function unlock() {
		$this->_locker->unlock($this->_resource);
	}
}
