<?php

/**
 * Copyright (c) 2021 brokiem
 * SimpleNPC is licensed under the GNU Lesser General Public License v3.0
 */

declare(strict_types=1);

namespace brokiem\snpc;

use brokiem\snpc\entity\BaseNPC;
use brokiem\snpc\entity\CustomHuman;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\math\Vector2;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\player\Player;

class EventHandler implements Listener {

	protected $tempVector;
	protected $movePlayerPacket;
	protected $moveActorPacket;
	protected $tempVector2;

	public function __construct(private SimpleNPC $plugin) {
		$this->tempVector = new Vector2(0, 0);
		$this->tempVector2 = new Vector2(0, 0);
		$this->movePlayerPacket = new MovePlayerPacket();
		$this->moveActorPacket = new MoveActorAbsolutePacket();
	}

    public function onDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();

        if ($entity instanceof CustomHuman || $entity instanceof BaseNPC) {
            $event->cancel();
        }

        if ($event instanceof EntityDamageByEntityEvent) {
            if ($entity instanceof CustomHuman || $entity instanceof BaseNPC) {
                $event->cancel();

                $damager = $event->getDamager();

                if ($damager instanceof Player) {
                    $entity->interact($damager);
                }
            }
        }
    }

    public function onDataPacketRecieve(DataPacketReceiveEvent $event): void {
        $player = $event->getOrigin()->getPlayer();
        $packet = $event->getPacket();

        if ($player !== null and $packet instanceof InventoryTransactionPacket && $packet->trData instanceof UseItemOnEntityTransactionData && $packet->trData->getActionType() === UseItemOnEntityTransactionData::ACTION_INTERACT) {
            $entity = $this->plugin->getServer()->getWorldManager()->findEntity($packet->trData->getActorRuntimeId());

            if ($entity instanceof BaseNPC || $entity instanceof CustomHuman) {
                $entity->interact($player);
            }
        }
    }

    public function onQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();

        if (isset($this->plugin->lastHit[$player->getName()])) {
            unset($this->plugin->lastHit[$player->getName()]);
        }

        if (isset($this->plugin->removeNPC[$player->getName()])) {
            unset($this->plugin->removeNPC[$player->getName()]);
        }

        if (isset($this->plugin->idPlayers[$player->getName()])) {
            unset($this->plugin->idPlayers[$player->getName()]);
        }
    }

    public function onMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();

        if ($this->plugin->getConfig()->get("enable-look-to-players", true)) {
            if ($event->getFrom()->distance($event->getTo()) < 1) {
                return;
            }

            foreach ($player->getWorld()->getNearbyEntities($player->getBoundingBox()->expandedCopy($this->plugin->getConfig()->get("max-look-distance", 8), $this->plugin->getConfig()->get("max-look-distance", 8), $this->plugin->getConfig()->get("max-look-distance", 8)), $player) as $entity) {
                if (($entity instanceof CustomHuman) or $entity instanceof BaseNPC) {
	                $location = $player->getLocation();
	                $entityLocation = $entity->getLocation();
	                $angle = atan2($location->z - $entityLocation->z, $location->x - $entityLocation->x);
                    $yaw = (($angle * 180) / M_PI) - 90;

	                $this->tempVector->x = $location->x;
	                $this->tempVector->y = $location->z;

	                $this->tempVector2->x = $entityLocation->x;
	                $this->tempVector2->y = $entityLocation->z;

	                $angle = atan2(($this->tempVector2)->distance($this->tempVector), $location->y - $entityLocation->y);
                    $pitch = (($angle * 180) / M_PI) - 90;

                    if ($entity instanceof CustomHuman and !$entity->canWalk() and $entity->canLookToPlayers()) {
	                    $pk = $this->movePlayerPacket;
                        $pk->actorRuntimeId = $entity->getId();
	                    $pk->position = $entityLocation->add(0, $entity->getEyeHeight(), 0);
                        $pk->yaw = $yaw;
                        $pk->pitch = $pitch;
                        $pk->headYaw = $yaw;
                        $pk->onGround = $entity->onGround;
	                    $player->getNetworkSession()->sendDataPacket($pk, true);
                    } elseif ($entity instanceof BaseNPC and $entity->canLookToPlayers()) {
	                    $pk = $this->moveActorPacket;
                        $pk->actorRuntimeId = $entity->getId();
	                    $pk->position = $entityLocation->asVector3();
                        $pk->pitch = $pitch;
                        $pk->yaw = $yaw;
                        $pk->headYaw = $yaw;
	                    $player->getNetworkSession()->sendDataPacket($pk, true);
                    }
                }
            }
        }
    }
}