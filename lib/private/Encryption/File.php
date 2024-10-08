<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Encryption;

use OCA\Files_External\Service\GlobalStoragesService;
use OCP\App\IAppManager;
use OCP\Cache\CappedMemoryCache;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Share\IManager;

class File implements \OCP\Encryption\IFile {
	protected Util $util;
	private IRootFolder $rootFolder;
	private IManager $shareManager;

	/**
	 * Cache results of already checked folders
	 * @var CappedMemoryCache<array>
	 */
	protected CappedMemoryCache $cache;
	private ?IAppManager $appManager = null;

	public function __construct(Util $util,
		IRootFolder $rootFolder,
		IManager $shareManager) {
		$this->util = $util;
		$this->cache = new CappedMemoryCache();
		$this->rootFolder = $rootFolder;
		$this->shareManager = $shareManager;
	}

	public function getAppManager(): IAppManager {
		// Lazy evaluate app manager as it initialize the db too early otherwise
		if ($this->appManager) {
			return $this->appManager;
		}
		$this->appManager = \OCP\Server::get(IAppManager::class);
		return $this->appManager;
	}

	/**
	 * Get list of users with access to the file
	 *
	 * @param string $path to the file
	 * @return array{users: string[], public: bool}
	 */
	public function getAccessList($path) {
		// Make sure that a share key is generated for the owner too
		[$owner, $ownerPath] = $this->util->getUidAndFilename($path);

		// always add owner to the list of users with access to the file
		$userIds = [$owner];

		if (!$this->util->isFile($owner . '/' . $ownerPath)) {
			return ['users' => $userIds, 'public' => false];
		}

		$ownerPath = substr($ownerPath, strlen('/files'));
		$userFolder = $this->rootFolder->getUserFolder($owner);
		try {
			$file = $userFolder->get($ownerPath);
		} catch (NotFoundException $e) {
			$file = null;
		}
		$ownerPath = $this->util->stripPartialFileExtension($ownerPath);

		// first get the shares for the parent and cache the result so that we don't
		// need to check all parents for every file
		$parent = dirname($ownerPath);
		$parentNode = $userFolder->get($parent);
		if (isset($this->cache[$parent])) {
			$resultForParents = $this->cache[$parent];
		} else {
			$resultForParents = $this->shareManager->getAccessList($parentNode);
			$this->cache[$parent] = $resultForParents;
		}
		$userIds = array_merge($userIds, $resultForParents['users']);
		$public = $resultForParents['public'] || $resultForParents['remote'];


		// Find out who, if anyone, is sharing the file
		if ($file !== null) {
			$resultForFile = $this->shareManager->getAccessList($file, false);
			$userIds = array_merge($userIds, $resultForFile['users']);
			$public = $resultForFile['public'] || $resultForFile['remote'] || $public;
		}

		// check if it is a group mount
		if ($this->getAppManager()->isEnabledForUser('files_external')) {
			/** @var GlobalStoragesService $storageService */
			$storageService = \OC::$server->get(GlobalStoragesService::class);
			$storages = $storageService->getAllStorages();
			foreach ($storages as $storage) {
				if ($storage->getMountPoint() == substr($ownerPath, 0, strlen($storage->getMountPoint()))) {
					$mountedFor = $this->util->getUserWithAccessToMountPoint($storage->getApplicableUsers(), $storage->getApplicableGroups());
					$userIds = array_merge($userIds, $mountedFor);
				}
			}
		}

		// Remove duplicate UIDs
		$uniqueUserIds = array_unique($userIds);

		return ['users' => $uniqueUserIds, 'public' => $public];
	}
}
