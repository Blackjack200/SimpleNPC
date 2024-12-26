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

class URLToCapeTask extends AsyncTask {

    public function __construct(private string $url, private string $dataPath, CustomHuman $npc, private string $player) {
        $this->storeLocal("snpc_urltocape", [$npc]);
    }

    public function onRun(): void {
        $uniqId = uniqid('cape', true);
        $parse = parse_url($this->url, PHP_URL_PATH);

	    if ($parse === null || $parse === false) {
		    return;
	    }

	    $extension = pathinfo($parse, PATHINFO_EXTENSION);
	    $data = Internet::getURL($this->url);

	    if ($data === null || strtolower($extension) !== "png") {
		    return;
	    }

	    $imageData = $data->getBody();

	    $file = Path::join($this->dataPath, "$uniqId.png");
	    file_put_contents($file, $imageData);

	    $img = URLToSkinTask::getSkinDataFromPNG($file);

	    if ($img === null) {
		    if (is_file($file)) {
			   // unlink($file);
		    }
		    return;
	    }

	    $this->setResult($img);

	    if (is_file($file)) {
		   // unlink($file);
	    }
    }

    public function onCompletion(): void {
        /** @var CustomHuman $npc */
        [$npc] = $this->fetchLocal("snpc_urltocape");
        $player = Server::getInstance()->getPlayerExact($this->player);

	    if ($player === null || !$player->isOnline()) {
		    return;
	    }

        if ($this->getResult() === null) {
            $player->sendMessage(TextFormat::RED . "Set Cape failed! Invalid link detected (the link doesn't contain images)");
            return;
        }

        if (strlen($this->getResult()) !== 8192) {
            $player->sendMessage(TextFormat::RED . "Set Cape failed! Invalid cape detected [bytes=" . strlen($this->getResult()) . "] [supported=8192]");
            return;
        }

	    $skin = $npc->getSkin();
	    $npc->setSkin(new Skin(
		    $skin->getSkinId(),
		    $skin->getSkinData(),
		    (string) $this->getResult(),
		    $skin->getGeometryName(),
		    $skin->getGeometryData()
	    ));
        $npc->sendSkin();

        $player->sendMessage(TextFormat::GREEN . "Successfull set cape to npc (ID: {$npc->getId()})");
    }
}