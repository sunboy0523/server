<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Louis Chmn <louis@chmn.me>
 *
 * @author Louis Chmn <louis@chmn.me>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Files\Controller;

use OC\AppFramework\Bootstrap\Coordinator;
use OC\Files\Node\Node;
use OCP\AppFramework\Http\ZipResponse;
use OCP\AppFramework\Controller;
use OCP\Files\File;
use OCP\IRequest;

class DownloadController extends Controller {
	private Coordinator $coordinator;

	public function __construct(
		string $appName,
		IRequest $request,
		Coordinator $coordinator,
	) {
		parent::__construct($appName, $request);

		$this->request = $request;
		$this->coordinator = $coordinator;
	}

	/**
	 * @NoAdminRequired
	 */
	public function index(array $files): ZipResponse {
		$context = $this->coordinator->getRegistrationContext();
		$providers = $context->getFileDownloadProviders();

		/** @var array<string, Node[]> */
		$nodes = [];

		foreach ($files as $filePath) {
			foreach ($providers as $provider) {
				try {
					$nodes[$filePath] = $provider->getNode($filePath);
				} catch (\Exception $ex) {
					// TODO: Log warning.
				}
			}
		}

		$response = new ZipResponse($this->request, 'download');

		foreach ($nodes as $filePath => $node) {
			// TODO: handle folders?
			if ($node instanceof File) {
				// TODO: maybe trim common prefix amoung files.
				$response->addResource($node->getContent(), $filePath, $node->getSize());
			}
		}

		return $response;
	}
}
