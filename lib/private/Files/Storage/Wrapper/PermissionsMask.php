<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files\Storage\Wrapper;

use OC\Files\Cache\Wrapper\CachePermissionsMask;
use OCP\Constants;

/**
 * Mask the permissions of a storage
 *
 * This can be used to restrict update, create, delete and/or share permissions of a storage
 *
 * Note that the read permissions can't be masked
 */
class PermissionsMask extends Wrapper {
	/**
	 * @var int the permissions bits we want to keep
	 */
	private $mask;

	/**
	 * @param array $arguments ['storage' => $storage, 'mask' => $mask]
	 *
	 * $storage: The storage the permissions mask should be applied on
	 * $mask: The permission bits that should be kept, a combination of the \OCP\Constant::PERMISSION_ constants
	 */
	public function __construct($arguments) {
		parent::__construct($arguments);
		$this->mask = $arguments['mask'];
	}

	private function checkMask($permissions) {
		return ($this->mask & $permissions) === $permissions;
	}

	public function isUpdatable($path) {
		return $this->checkMask(Constants::PERMISSION_UPDATE) and parent::isUpdatable($path);
	}

	public function isCreatable($path) {
		return $this->checkMask(Constants::PERMISSION_CREATE) and parent::isCreatable($path);
	}

	public function isDeletable($path) {
		return $this->checkMask(Constants::PERMISSION_DELETE) and parent::isDeletable($path);
	}

	public function isSharable($path) {
		return $this->checkMask(Constants::PERMISSION_SHARE) and parent::isSharable($path);
	}

	public function getPermissions($path) {
		return $this->storage->getPermissions($path) & $this->mask;
	}

	public function rename($source, $target) {
		//This is a rename of the transfer file to the original file
		if (dirname($source) === dirname($target) && strpos($source, '.ocTransferId') > 0) {
			return $this->checkMask(Constants::PERMISSION_CREATE) and parent::rename($source, $target);
		}
		return $this->checkMask(Constants::PERMISSION_UPDATE) and parent::rename($source, $target);
	}

	public function copy($source, $target) {
		return $this->checkMask(Constants::PERMISSION_CREATE) and parent::copy($source, $target);
	}

	public function touch($path, $mtime = null) {
		$permissions = $this->file_exists($path) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		return $this->checkMask($permissions) and parent::touch($path, $mtime);
	}

	public function mkdir($path) {
		return $this->checkMask(Constants::PERMISSION_CREATE) and parent::mkdir($path);
	}

	public function rmdir($path) {
		return $this->checkMask(Constants::PERMISSION_DELETE) and parent::rmdir($path);
	}

	public function unlink($path) {
		return $this->checkMask(Constants::PERMISSION_DELETE) and parent::unlink($path);
	}

	public function file_put_contents($path, $data) {
		$permissions = $this->file_exists($path) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		return $this->checkMask($permissions) ? parent::file_put_contents($path, $data) : false;
	}

	public function fopen($path, $mode) {
		if ($mode === 'r' or $mode === 'rb') {
			return parent::fopen($path, $mode);
		} else {
			$permissions = $this->file_exists($path) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
			return $this->checkMask($permissions) ? parent::fopen($path, $mode) : false;
		}
	}

	public function getCache($path = '', $storage = null) {
		if (!$storage) {
			$storage = $this;
		}
		$sourceCache = parent::getCache($path, $storage);
		return new CachePermissionsMask($sourceCache, $this->mask);
	}

	public function getMetaData($path) {
		$data = parent::getMetaData($path);

		if ($data && isset($data['permissions'])) {
			$data['scan_permissions'] = $data['scan_permissions'] ?? $data['permissions'];
			$data['permissions'] &= $this->mask;
		}
		return $data;
	}

	public function getScanner($path = '', $storage = null) {
		if (!$storage) {
			$storage = $this->storage;
		}
		return parent::getScanner($path, $storage);
	}

	public function getDirectoryContent($directory): \Traversable {
		foreach ($this->getWrapperStorage()->getDirectoryContent($directory) as $data) {
			$data['scan_permissions'] = $data['scan_permissions'] ?? $data['permissions'];
			$data['permissions'] &= $this->mask;

			yield $data;
		}
	}
}
