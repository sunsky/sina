<?php
interface CLock_LockerInterface {
	/**
	 * @param string $namespace
	 * @param string $name
	 * @param int $limit
	 * @return resource
	 */
	public function getResource($namespace, $name, $limit = 0);
	/**
	 * @param $resource
	 */
	public function lock($resource);
	/**
	 * @param unknown_type $resource
	 */
	public function unlock($resource);
}
