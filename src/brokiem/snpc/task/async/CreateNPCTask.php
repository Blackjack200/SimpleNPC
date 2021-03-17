<?php /** @noinspection NotOptimalIfConditionsInspection */
declare(strict_types=1);

namespace brokiem\snpc\task\async;

use brokiem\snpc\event\SNPCCreationEvent;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\entity\Skin;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class CreateNPCTask extends AsyncTask
{
    /** @var string */
    private $skinUrl;
    /** @var ?string */
    private $nametag;
    /** @var bool */
    private $canWalk;
    /** @var string */
    private $username;
    /** @var string */
    private $dataPath;

    public function __construct(?string $nametag, string $username, string $dataPath, bool $canWalk = false, ?string $skinUrl = null)
    {
        $this->username = $username;
        $this->nametag = $nametag;
        $this->canWalk = $canWalk;
        $this->skinUrl = $skinUrl;
        $this->dataPath = $dataPath;
    }

    public function onRun(): void
    {
        if ($this->skinUrl !== null) {
            $uniqId = uniqid($this->nametag, true);
            $contents = file_get_contents($this->skinUrl);
            $extension = pathinfo(parse_url($this->skinUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
            file_put_contents($this->dataPath . $uniqId . ".$extension", $contents);

            $file = $this->dataPath . $uniqId . ".$extension";

            if ($extension === "png") {
                $img = imagecreatefrompng($file);
                $bytes = '';
                for ($y = 0; $y < imagesy($img); $y++) {
                    for ($x = 0; $x < imagesx($img); $x++) {
                        $rgba = @imagecolorat($img, $x, $y);
                        $a = ((~(($rgba >> 24))) << 1) & 0xff;
                        $r = ($rgba >> 16) & 0xff;
                        $g = ($rgba >> 8) & 0xff;
                        $b = $rgba & 0xff;
                        $bytes .= chr($r) . chr($g) . chr($b) . chr($a);
                    }
                }

                @imagedestroy($img);
                $this->setResult($bytes);
            } elseif ($extension === "jpg" or $extension === "jpeg") {
                $img = imagecreatefromjpeg($file);
                $bytes = '';
                for ($y = 0; $y < imagesy($img); $y++) {
                    for ($x = 0; $x < imagesx($img); $x++) {
                        $rgba = @imagecolorat($img, $x, $y);
                        $a = ((~($rgba >> 24)) << 1) & 0xff;
                        $r = ($rgba >> 16) & 0xff;
                        $g = ($rgba >> 8) & 0xff;
                        $b = $rgba & 0xff;
                        $bytes .= chr($r) . chr($g) . chr($b) . chr($a);
                    }
                }

                @imagedestroy($img);
                $this->setResult($bytes);
            }

            unlink($file);
        }
    }

    public function onCompletion(Server $server): void
    {
        $player = $server->getPlayerExact($this->username);

        if ($player === null) {
            return;
        }

        $nbt = Entity::createBaseNBT($player, null, $player->getYaw(), $player->getPitch());
        $nbt->setTag($player->namedtag->getTag("Skin"));
        $nbt->setTag(new CompoundTag("commands", []));

        $entity = new Human($player->getLevel(), $nbt);

        if (!$entity instanceof Human) {
            $player->sendMessage('Entity not human');
            return;
        }

        if (!$this->nametag) {
            $entity->setNameTag($this->nametag);
            $entity->setNameTagAlwaysVisible();
        }

        if (!$this->getResult() or $this->skinUrl !== null) {
            $entity->setSkin(new Skin($player->getSkin()->getSkinId(), $this->getResult()));
        } else {
            $entity->setSkin($player->getSkin());
        }

        $entity->spawnToAll();
        (new SNPCCreationEvent($entity))->call();
    }
}