<?php /** @noinspection RedundantElseClauseInspection */

declare(strict_types=1);

namespace brokiem\snpc\commands;

use brokiem\snpc\entity\BaseNPC;
use brokiem\snpc\entity\CustomHuman;
use brokiem\snpc\manager\form\FormManager;
use brokiem\snpc\manager\NPCManager;
use brokiem\snpc\SimpleNPC;
use brokiem\snpc\task\async\URLToSkinTask;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class Commands extends Command implements PluginOwned {

    public function __construct(string $name, private SimpleNPC $owner) {
        parent::__construct($name, "SimpleNPC commands");
		$this->setPermission("simplenpc.command");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$this->testPermission($sender)) {
            return true;
        }

        /** @var SimpleNPC $plugin */
        $plugin = $this->getOwningPlugin();

        if (isset($args[0])) {
	        $playerName = $sender->getName();
	        switch (strtolower($args[0])) {
                case "ui":
                    if (!$sender instanceof Player) {
                        $sender->sendMessage("Only player can run this command");
                        return true;
                    }

                    FormManager::getInstance()->sendUIForm($sender);
                    break;
                case "reload":
                    $plugin->initConfiguration();
                    $sender->sendMessage(TextFormat::GREEN . "SimpleNPC Config reloaded successfully!");
                    break;
                case "id":
	                if (!isset($plugin->idPlayers[$playerName])) {
		                $plugin->idPlayers[$playerName] = true;
                        $sender->sendMessage(TextFormat::DARK_GREEN . "Hit the npc that you want to see the ID");
                    } else {
		                unset($plugin->idPlayers[$playerName]);
                        $sender->sendMessage(TextFormat::GREEN . "Tap to get NPC ID has been canceled");
                    }
                    break;
                case "spawn":
                case "add":
                    if (!$sender instanceof Player) {
	                    $sender->sendMessage(TextFormat::RED . "Only player can run this command!");
                        return true;
                    }

                    if (isset($args[1])) {
	                    $npcType = strtolower($args[1]) . "_snpc";
	                    $prettyNpcTypeName = ucfirst($args[1]);
	                    $isUrl = false;
	                    if (!array_key_exists($npcType, SimpleNPC::getInstance()->getRegisteredNPC())) {
		                    $sender->sendMessage(TextFormat::RED . "Invalid entity type $prettyNpcTypeName or entity not registered!");
		                    return true;
	                    }
	                    if (is_a(SimpleNPC::getInstance()->getRegisteredNPC()[$npcType][0], CustomHuman::class, true)) {
		                    $skinOrigin = $playerName;
		                    $targetSkin = $sender->getSkin();
		                    if (isset($args[3])) {
			                    $arg = $args[3];
			                    $targ = Server::getInstance()->getPlayerByPrefix($arg);
			                    if ($targ !== null) {
				                    $skinOrigin = $targ->getName();
				                    $targetSkin = $targ->getSkin();
			                    } else {
				                    $isUrl = true;
			                    }
		                    }
		                    $sender->sendMessage(TextFormat::DARK_GREEN . "Creating $prettyNpcTypeName NPC with nametag $args[2] using skin $skinOrigin from for you...");
		                    $id = NPCManager::getInstance()->spawnNPC($npcType, $sender, $args[2], null, $targetSkin->getSkinData());
		                    if ($id !== null) {
			                    $npc = $sender->getServer()->getWorldManager()->findEntity($id);
			                    if ($npc instanceof CustomHuman && $isUrl) {
				                    $plugin->getServer()->getAsyncPool()->submitTask(new URLToSkinTask($sender->getName(), $plugin->getDataFolder(), (string) $args[3], $npc));
			                    }
		                    }
	                    } else {
		                    if (isset($args[2])) {
			                    NPCManager::getInstance()->spawnNPC($npcType, $sender, $args[2]);
			                    $sender->sendMessage(TextFormat::DARK_GREEN . "Creating $prettyNpcTypeName NPC with nametag $args[2] for you...");
			                    return true;
		                    }
		                    $sender->sendMessage(TextFormat::DARK_GREEN . "Creating $prettyNpcTypeName NPC without nametag for you...");
		                    NPCManager::getInstance()->spawnNPC($npcType, $sender);
	                    }

                    } else {
	                    $sender->sendMessage(TextFormat::RED . "Usage: /snpc spawn <type> optional: <nametag> <skinUrl/usingSkin>");
                    }
                    break;
                case "delete":
                case "remove":
                    if (isset($args[1]) && is_numeric($args[1])) {
                        $entity = $plugin->getServer()->getWorldManager()->findEntity((int)$args[1]);

                        if ($entity instanceof BaseNPC || $entity instanceof CustomHuman) {
                            if ($entity->despawn()) {
                                $sender->sendMessage(TextFormat::GREEN . "The NPC was successfully removed!");
                            } else {
                                $sender->sendMessage(TextFormat::YELLOW . "The NPC was failed removed! (File not found)");
                            }
                            return true;
                        }

                        $sender->sendMessage(TextFormat::YELLOW . "SimpleNPC Entity with ID: " . $args[1] . " not found!");
                        return true;
                    }

		        if (!isset($plugin->removeNPC[$playerName])) {
			        $plugin->removeNPC[$playerName] = true;
                        $sender->sendMessage(TextFormat::DARK_GREEN . "Hit the npc that you want to delete or remove");
                        return true;
                    }

		        unset($plugin->removeNPC[$playerName]);
                    $sender->sendMessage(TextFormat::GREEN . "Remove npc by hitting has been canceled");
                    break;
                case "edit":
                case "manage":
                    if (!$sender instanceof Player) {
	                    $sender->sendMessage(TextFormat::RED . "Only player can run this command!");
                        return true;
                    }

                    if (!isset($args[1]) || !is_numeric($args[1])) {
                        $sender->sendMessage(TextFormat::RED . "Usage: /snpc edit <id>");
                        return true;
                    }

                    FormManager::getInstance()->sendEditForm($sender, $args, (int)$args[1]);
                    break;
                case "list":
	                $list = FormManager::getInstance()->getPrettyNpcList($plugin);
	                $sender->sendMessage($list);
	                break;
                case "help":
	                $this->sendHelp($sender);
                    break;
                default:
                    $sender->sendMessage(TextFormat::RED . "Subcommand '$args[0]' not found! Try '/snpc help' for help.");
                    break;
            }
        } else {
	        if ($sender instanceof Player) {
		        FormManager::getInstance()->sendUIForm($sender);
		        return true;
	        }
	        $this->sendHelp($sender);
        }

        return true;
    }

    public function getOwningPlugin(): Plugin {
        return $this->owner;
    }

	private function sendHelp(CommandSender $sender) : void {
		$sender->sendMessage(<<<HELP
§7---- ---- ---- - ---- ---- ----
§eCommand List:
§2» /snpc spawn <type> <nametag> <skinUrl/usingSkin>
§2» /snpc edit <id>
§2» /snpc reload
§2» /snpc ui
§2» /snpc remove <id>
§2» /snpc list
§7---- ---- ---- - ---- ---- ----
HELP
		);
	}
}