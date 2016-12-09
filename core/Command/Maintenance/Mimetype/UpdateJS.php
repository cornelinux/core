<?php
/**
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <rullzer@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud GmbH.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\Core\Command\Maintenance\Mimetype;

use OC\Files\Type\Detection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCP\Files\IMimeTypeDetector;

class UpdateJS extends Command {

	/** @var IMimeTypeDetector */
	protected $mimetypeDetector;

	public function __construct(
		Detection $mimetypeDetector
	) {
		parent::__construct();
		$this->mimetypeDetector = $mimetypeDetector;
	}

	protected function configure() {
		$this
			->setName('maintenance:mimetype:update-js')
			->setDescription('Update mimetypelist.js');
	}

	/**
	 * @return array
	 */
	private function getFiles() {
		$dir = new \DirectoryIterator(\OC::$SERVERROOT . '/core/img/filetypes');

		$files = [];
		foreach($dir as $fileInfo) {
			if ($fileInfo->isFile()) {
				$files[] = preg_replace('/.[^.]*$/', '', $fileInfo->getFilename());
			}
		}

		$files = array_values(array_unique($files));
		sort($files);
		return $files;
	}

	/**
	 * @param $themeDirectory
	 * @return array
	 */
	private function getFileTypeIcons($themeDirectory) {
		$fileTypeIcons = [];
		$fileTypeIconDirectory = $themeDirectory . '/core/img/filetypes';

		if (is_dir($fileTypeIconDirectory)) {
			$fileTypeIconFiles = new \DirectoryIterator($fileTypeIconDirectory);
			foreach ($fileTypeIconFiles as $fileTypeIconFile) {
				if ($fileTypeIconFile->isFile()) {
					$fileTypeIconName = preg_replace('/.[^.]*$/', '', $fileTypeIconFile->getFilename());
					$fileTypeIcons[] = $fileTypeIconName;
				}
			}
		}

		$fileTypeIcons = array_values(array_unique($fileTypeIcons));
		sort($fileTypeIcons);

		return $fileTypeIcons;
	}

	/**
	 * @return array
	 */
	private function getThemes() {
		return array_merge(
			$this->getAppThemes(),
			$this->getLegacyThemes()
		);
	}

	/**
	 * @return array
	 */
	private function getAppThemes() {
		$themes = [];

		$apps = \OC_App::getEnabledApps();

		foreach ($apps as $app) {
			if(\OC_App::isType($app, 'theme')) {
				$themes[$app] = $this->getFileTypeIcons(\OC_App::getAppPath($app));
			}
		}

		return $themes;
	}

	/**
	 * @return array
	 */
	private function getLegacyThemes() {
		$themes = [];

		$legacyThemeDirectories = new \DirectoryIterator(\OC::$SERVERROOT . '/themes/');

		foreach($legacyThemeDirectories as $legacyThemeDirectory) {
			if ($legacyThemeDirectory->isFile() || $legacyThemeDirectory->isDot()) {
				continue;
			}
			$themes[$legacyThemeDirectory->getFilename()] = $this->getFileTypeIcons(
				$legacyThemeDirectory->getPathname()
			);
		}

		return $themes;
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		file_put_contents(
			\OC::$SERVERROOT.'/core/js/mimetypelist.js',
			$this->generateMimeTypeListContent(
				$this->mimetypeDetector->getAllAliases(),
				$this->getFiles(),
				$this->getThemes()
			)
		);

		$output->writeln('<info>mimetypelist.js is updated</info>');
		return 0;
	}

	/**
	 * @param array $aliases
	 * @param array $files
	 * @param array $themes
	 * @return string
	 */
	private function generateMimeTypeListContent($aliases, $files, $themes) {
		$aliasesJson = json_encode($aliases, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		$filesJson = json_encode($files, JSON_PRETTY_PRINT);
		$themesJson = json_encode($themes, JSON_PRETTY_PRINT);

		$content = <<< MTLC
/**
* This file is automatically generated
* DO NOT EDIT MANUALLY!
*
* You can update the list of MimeType Aliases in config/mimetypealiases.json
* The list of files is fetched from core/img/filetypes
* To regenerate this file run ./occ maintenance:mimetype:update-js
*/
OC.MimeTypeList={
	aliases: $aliasesJson,
	files: $filesJson,
	themes: $themesJson
};
MTLC;

		return $content;
	}
}
