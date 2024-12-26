<?php

/**
 * Copyright (c) 2021 brokiem
 * SimpleNPC is licensed under the GNU Lesser General Public License v3.0
 */

declare(strict_types=1);

namespace brokiem\snpc\task\async;

use brokiem\snpc\entity\CustomHuman;
use pocketmine\entity\Skin;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Internet;
use pocketmine\utils\TextFormat;
use Symfony\Component\Filesystem\Path;

class URLToSkinTask extends AsyncTask {

	public function __construct(private string $username, private string $dataPath, private string $skinUrl, CustomHuman $human) {
		$this->storeLocal("snpc_urltoskin", $human);
	}

	public function onRun() : void {
		$uniqId = uniqid("skin-change", true);
		$parse = parse_url($this->skinUrl, PHP_URL_PATH);

		if ($parse === null || $parse === false) {
			return;
		}

		$extension = pathinfo($parse, PATHINFO_EXTENSION);
		$data = Internet::getURL($this->skinUrl);

		if ($data === null || strtolower($extension) !== "png") {
			return;
		}

		$imageData = $data->getBody();

		$file = Path::join($this->dataPath, "$uniqId.png");
		file_put_contents($file, $imageData);

		$img = self::getSkinDataFromPNG($file);

		if ($img === null) {
			if (is_file($file)) {
				unlink($file);
			}
			return;
		}

		$this->setResult($img);

		if (is_file($file)) {
			unlink($file);
		}
	}

	public static function getSkinDataFromPNG(string $path) : ?string {
		$img = imagecreatefrompng($path);
		if ($img === false) {
			return null;
		}

		if (!imagepalettetotruecolor($img)) {
			imagedestroy($img);
			return null;
		}

		[$k, $l] = getimagesize($path);
		$bytes = '';

		for ($y = 0; $y < $l; ++$y) {
			for ($x = 0; $x < $k; ++$x) {
				$argb = imagecolorat($img, $x, $y);
				$bytes .= chr(($argb >> 16) & 0xff) . chr(($argb >> 8) & 0xff) . chr($argb & 0xff) . chr((~($argb >> 24) << 1) & 0xff);
			}
		}

		imagedestroy($img);
		return $bytes;
	}


	public function onCompletion() : void {
		$player = Server::getInstance()->getPlayerExact($this->username);
		/** @var CustomHuman $human */
		$human = $this->fetchLocal("snpc_urltoskin");

		if ($player === null || !$player->isOnline()) {
			return;
		}

		$player->saveNBT();

		$skinData = $this->getResult();

		if ($skinData === null) {
			$player->sendMessage(TextFormat::RED . "Set Skin failed! Invalid link detected (the link doesn't contain images)");
			return;
		}

		$skin = $human->getSkin();
		$human->setSkin(new Skin(
			$skin->getSkinId(),
			$skinData,
			$skin->getCapeData(),
			$skin->getGeometryName(),
			$skin->getGeometryData()
		));
		$human->sendSkin();
		$player->sendMessage(TextFormat::GREEN . "Successfull set skin to npc (ID: {$human->getId()})");
	}
}