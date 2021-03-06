<?php

/**
 * ownCloud - Updater plugin
 *
 * @author Victor Dubiniuk
 * @copyright 2013 Victor Dubiniuk victor.dubiniuk@gmail.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */

namespace OCA\Updater;

abstract class Location {

	// Path to current instance sources
	protected $oldBase;
	// Path to new instance sources
	protected $newBase;
	// Type of sources (3rdparty | apps | core)
	protected $type = 'generic';
	// Already moved items are collected here;
	protected $done = array();

	public function __construct($oldBase, $newBase) {
		$this->oldBase = $oldBase;
		$this->newBase = $newBase;
	}

	// Move sources 
	public function update($tmpDir = '') {
		Helper::mkdir($tmpDir, true);
		$collected = $this->collect();

		try {
			foreach ($collected['old'] as $src) {
				$dst = str_replace($this->oldBase, $tmpDir, $src);
				Helper::move($src, $dst);

				// ! reverted intentionally
				$this->done [] = array(
					'dst' => $src,
					'src' => $dst
				);
			}

			foreach ($collected['new'] as $src) {
				$dst = str_replace($this->newBase, $this->oldBase, $src);
				Helper::move($src, $dst);
			}

			$this->finalize();
		} catch (\Exception $e) {
			$this->rollback(true);
			throw $e;
		}
	}

	// Extra steps needed
	protected function finalize() {
		App::log('Success: ' . $this->type, \OC_Log::INFO);
	}

	// Move sources back on Error
	public final function rollback($log = false) {
		if ($log) {
			App::log('Something went wrong for ' . $this->type . '. Rolling back.');
		}
		foreach ($this->done as $item) {
			Helper::copyr($item['src'], $item['dst'], false);
		}
	}

	// Aggregate current sources
	public function collect($dryRun = false) {
		$oldSources = $this->filterOld(
				Helper::scandir($this->oldBase)
		);

		$collected = array(
			'old' => $this->toAbsolute($this->oldBase, $oldSources),
			'new' => array()
		);

		if (!$dryRun) {
			$newSources = $this->filterNew(
					Helper::scandir($this->newBase)
			);
			$collected['new'] = $this->toAbsolute($this->newBase, $newSources);
		}

		return $collected;
	}

	protected function toAbsolute($base, $pathArray) {
		$result = array();
		foreach ($pathArray as $path) {
			// There is a little sense to make these entries absolute
			if (!in_array($path, array('.', '..'))) {
				$result [$path] = realpath($base . '/' . $path);
			}
		}
		return $result;
	}

	// Filter input 
	protected function filterOld($pathArray) {
		return $pathArray;
	}

	// Filter input 
	protected function filterNew($pathArray) {
		return $pathArray;
	}
}
