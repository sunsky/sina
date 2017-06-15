<?php
require_once 'PI/Util/Lock/LockerInterface.php';
class PI_Util_Lock_SemaphoreAdapter implements PI_Util_Lock_LockerInterface {
	public function lock($resource) {
		sem_acquire($resource);
	}
	/**
	 * @see PI_Util_Lock_LockerInterface::unlock()
	 */
	public function unlock($resource) {
		 sem_release($resource);
	}
	/**
	 * @see PI_Util_Lock_LockerInterface::getResource()
	 */
	public function getResource($namespace, $name, $limit = 0) {
		$id = crc32($namespace.':'.$name);
		if ($limit) {
			$id = $id % $limit;
		}
		$result = sem_get($id);
		if ($result === false) {
			throw new RuntimeException('fail to create semaphore resource');
		}
		return $result;
	}
}