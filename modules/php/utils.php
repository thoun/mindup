<?php

trait UtilTrait {

    //////////////////////////////////////////////////////////////////////////////
    //////////// Utility functions
    ////////////

    function array_find(array $array, callable $fn) {
        foreach ($array as $value) {
            if($fn($value)) {
                return $value;
            }
        }
        return null;
    }

    function array_find_key(array $array, callable $fn) {
        foreach ($array as $key => $value) {
            if($fn($value)) {
                return $key;
            }
        }
        return null;
    }

    function array_some(array $array, callable $fn) {
        foreach ($array as $value) {
            if($fn($value)) {
                return true;
            }
        }
        return false;
    }
    
    function array_every(array $array, callable $fn) {
        foreach ($array as $value) {
            if(!$fn($value)) {
                return false;
            }
        }
        return true;
    }

    function setGlobalVariable(string $name, /*object|array*/ $obj) {
        /*if ($obj == null) {
            throw new \Error('Global Variable null');
        }*/
        $jsonObj = json_encode($obj);
        $this->DbQuery("INSERT INTO `global_variables`(`name`, `value`)  VALUES ('$name', '$jsonObj') ON DUPLICATE KEY UPDATE `value` = '$jsonObj'");
    }

    function getGlobalVariable(string $name, $asArray = null) {
        $json_obj = $this->getUniqueValueFromDB("SELECT `value` FROM `global_variables` where `name` = '$name'");
        if ($json_obj) {
            $object = json_decode($json_obj, $asArray);
            return $object;
        } else {
            return null;
        }
    }

    function deleteGlobalVariable(string $name) {
        $this->DbQuery("DELETE FROM `global_variables` where `name` = '$name'");
    }

    function getPlayersIds() {
        return array_keys($this->loadPlayersBasicInfos());
    }

    function getRoundCardCount() {
        return count($this->getPlayersIds()) + 2;
    }

    function getPlayerName(int $playerId) {
        return self::getUniqueValueFromDB("SELECT player_name FROM player WHERE player_id = $playerId");
    }

    function getPlayerScore(int $playerId) {
        return intval($this->getUniqueValueFromDB("SELECT player_score FROM player where `player_id` = $playerId"));
    }

    function getPlayerSelectedCard(int $playerId) {
        $val = $this->getUniqueValueFromDB("SELECT player_selected_card FROM player where `player_id` = $playerId");
        return $val != null ? intval($val) : null;
    }

    function setPlayerSelectedCard(int $playerId, /*int|null*/ $selectedCard) {
        self::DbQuery("update player set player_selected_card = ".($selectedCard !== null ? $selectedCard : 'NULL')." where `player_id` = $playerId");
    }

    function getCardById(int $id) {
        $sql = "SELECT * FROM `card` WHERE `card_id` = $id";
        $dbResults = $this->getCollectionFromDb($sql);
        $cards = array_map(fn($dbCard) => new Card($dbCard), array_values($dbResults));
        return count($cards) > 0 ? $cards[0] : null;
    }

    function getCardsByLocation(string $location, /*int|null*/ $location_arg = null, /*int|null*/ $type = null, /*int|null*/ $number = null) {
        $sql = "SELECT * FROM `card` WHERE `card_location` = '$location'";
        if ($location_arg !== null) {
            $sql .= " AND `card_location_arg` = $location_arg";
        }
        if ($type !== null) {
            $sql .= " AND `card_type` = $type";
        }
        if ($number !== null) {
            $sql .= " AND `card_type_arg` = $number";
        }
        $sql .= " ORDER BY `card_location_arg`";
        $dbResults = $this->getCollectionFromDb($sql);
        return array_map(fn($dbCard) => new Card($dbCard), array_values($dbResults));
    }

    function setupCards(array $playersIds) {
        // number cards
        $cards = [];
        for ($i = 1; $i <= 60; $i++) {
            $cards[] = [ 'type' => 1, 'type_arg' => $i, 'nbr' => 1 ];
        }
        $this->cards->createCards($cards, 'deck');
        $this->cards->shuffle('deck');

        foreach ($playersIds as $playerId) {
            $this->cards->pickCards(7, 'deck', $playerId);
        }

        $tableDb = $this->cards->getCardsOnTop(count($playersIds), 'deck');
        $table = array_map(fn($dbCard) => new Card($dbCard), array_values($tableDb));
        usort($table, fn($a, $b) => $a->number - $b->number);
        foreach ($table as $index => $card) {
            $this->cards->moveCard($card->id, 'table', $index);
        }
    }

    function getCol(int $playerId, int $color) {
        $cards = $this->getCardsByLocation('score'.$playerId);

        $card = $this->array_find($cards, fn($c) => $c->color == $color);
        if ($card != null) {
            return $card->locationArg;
        } else {
            $maxLocationArg = -1;

            foreach($cards as $c) {
                if ($c->locationArg > $maxLocationArg) {
                    $maxLocationArg = $c->locationArg;
                }
            }

            return $maxLocationArg + 1;
        }
    }
    
}
