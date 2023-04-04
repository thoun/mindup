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

    function getPlayerScoreAux(int $playerId) {
        return intval($this->getUniqueValueFromDB("SELECT player_score_aux FROM player where `player_id` = $playerId"));
    }

    function getPlayerSelectedCard(int $playerId) {
        $cards = $this->getCardsByLocation('selected', $playerId);
        return $cards != null ? $cards[0] : null;
    }

    function setPlayerSelectedCard(int $playerId, /*int|null*/ $selectedCardId) {
        $selected = $selectedCardId !== null;
        $card = $selected ? $this->getCardById($selectedCardId) : $this->getPlayerSelectedCard($playerId);
        $this->cards->moveCard($card->id, $selected ? 'selected' : 'hand', $playerId);
        $this->notifyAllPlayers('selectedCard', '', [
            'playerId' => $playerId,
            'card' => Card::onlyId($card),
            'cancel' => !$selected,
        ]);
        $this->notifyPlayer($playerId, 'selectedCard', '', [
            'playerId' => $playerId,
            'card' => $card,
            'cancel' => !$selected,
        ]);
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

    function getColByColor(array $scoredCards, int $color) {
        $scoreCardsOfColor = $this->array_find($scoredCards, fn($card) => $card->color == $color);

        return $scoreCardsOfColor !== null ? $scoreCardsOfColor->locationArg : null;
    }

    function getPlayerCardCountForColor(int $color) {
        $cardCountForColor = [];

        $playersIds = $this->getPlayersIds();
        foreach($playersIds as $playerId) {
            $scoredCards = $this->getCardsByLocation('score'.$playerId);
            $scoresCardsOfColor = array_values(array_filter($scoredCards, fn($card) => $card->color == $color));
            $cardCountForColor[$playerId] = count($scoresCardsOfColor);
        }

        return $cardCountForColor;
    }

    function getObjectivePoints(int $objective, array $scoredCards, array $cardsByColor, array $costs) {        
        switch ($objective) {
            case 1: return in_array(count($cardsByColor[ORANGE]), [1, 3]) ? 2 : 0;
            case 2: $col = $this->getColByColor($scoredCards, ORANGE); return $col !== null && in_array($costs[$col], [1, 2]) ? -2 : 0;
            case 3: return in_array(count($cardsByColor[BLUE]), [2, 4]) ? 2 : 0;
            case 4: 
                $blueCount = count($cardsByColor[BLUE]);
                return !$this->array_some($cardsByColor, fn($colorCards) => count($colorCards) > $blueCount) ? 2 : 0;
            case 5: 
                $currentPlayerCount = count($cardsByColor[PINK]);
                $cardCountForColor = $this->getPlayerCardCountForColor(PINK);
                return !$this->array_some($cardCountForColor, fn($cardCount) => $cardCount < $currentPlayerCount) == 3 ? -2 : 0;
            case 6: 
                $currentPlayerCount = count($cardsByColor[PINK]);
                $cardCountForColor = $this->getPlayerCardCountForColor(PINK);
                return !$this->array_some($cardCountForColor, fn($cardCount) => $cardCount > $currentPlayerCount) == 3 ? 2 : 0;
            case 7: 
                $lastColor = null;
                for ($i=0; $i<5; $i++) {
                    $scoresCardsOfColor = array_values(array_filter($scoredCards, fn($card) => $card->locationArg == $i));
                    if (count($scoresCardsOfColor) > 0) {
                        $lastColor = $scoresCardsOfColor[0]->color;
                    }
                }
                return $lastColor == GREEN ? 2 : 0;
            case 8: $col = $this->getColByColor($scoredCards, GREEN); return $col !== null && in_array($costs[$col], [4, 5]) ? 2 : 0;
            case 9: return count($cardsByColor[PURPLE]) >= count($cardsByColor[ORANGE]) ? 2 : 0;
            case 10: 
                $currentPlayerCount = count($cardsByColor[PURPLE]);
                $cardCountForColor = $this->getPlayerCardCountForColor(PURPLE);
                return !$this->array_some($cardCountForColor, fn($cardCount) => $cardCount > $currentPlayerCount) == 3 ? -2 : 0;
            case 11: return count(array_filter($cardsByColor, fn($cards) => count($cards) > 0)) == 5 ? 2 : 0;
            case 12: return count(array_filter($cardsByColor, fn($cards) => count($cards) > 0)) == 3 ? 2 : 0;
            case 13: return $this->array_some($cardsByColor, fn($colorCards) => count($colorCards) == 3) ? -2 : 0;
            case 14: return $this->array_some($cardsByColor, fn($colorCards) => count($colorCards) == 4) ? 2 : 0;
        }
        return 0;
    }

    function updatePlayerScore(int $playerId, array $costs, array $objectives) {
        $roundScore = 0;

        $scoredCards = $this->getCardsByLocation('score'.$playerId);
        $cardsByColor = [
            ORANGE => [],
            PINK => [],
            BLUE => [],
            GREEN => [],
            PURPLE => [],
        ];

        for ($i=0; $i<5; $i++) {
            $scoresCardsOfColor = array_values(array_filter($scoredCards, fn($card) => $card->locationArg == $i));
            foreach ($scoresCardsOfColor as $card) {
                $roundScore += $costs[$i] + $card->points;
                $cardsByColor[$card->color][] = $card;
            }
        }

        //$roundScoreBefore = $roundScore;
        foreach ($objectives as $objective) {
            $roundScore += $this->getObjectivePoints($objective, $scoredCards, $cardsByColor, $costs);
        }

        /*$debug = [];
        foreach ($objectives as $objective) {
            $debug[$objective] = $this->getObjectivePoints($objective, $scoredCards, $cardsByColor, $costs);
        }
        self::notifyAllPlayers('log', json_encode([$playerId, $roundScoreBefore, $debug, $roundScore]), []);*/

        $this->DbQuery("UPDATE player SET `player_score_aux` = $roundScore WHERE `player_id` = $playerId");

        return $this->getPlayerScore($playerId) + $roundScore;
    }
    
    function updateStats(int $playerId, int $cardPoints, int $scoreCardPoints) {
        $this->incStat($cardPoints, 'cardPoints');
        $this->incStat($cardPoints, 'cardPoints', $playerId);
        $this->incStat($scoreCardPoints, 'scoreCardPoints');
        $this->incStat($scoreCardPoints, 'scoreCardPoints', $playerId);
    }

    function getBonusObjectivesNumber() {
        return intval($this->getGameStateValue(BONUS_OBJECTIVES_OPTION));
    }

    function setBonusObjectives(bool $firstRound) {
        $number = $this->getBonusObjectivesNumber();

        if ($number == 0 || ($number >= 3 && !$firstRound)) {
            return;
        }

        $selectedLetters = [];
        $availableLetters = [0, 1, 2, 3, 4, 5, 6];

        for ($i = 0; $i < $number; $i++) {
            $index = bga_rand(1, count($availableLetters)) - 1;
            $selectedLetters[] = $availableLetters[$index];
            array_splice($availableLetters, $index, 1);
        }

        $objectives = array_map(fn($letter) => $letter * 2 + bga_rand(1, 2), $selectedLetters);

        $this->setGlobalVariable(BONUS_OBJECTIVES, $objectives);

        if (!$firstRound) {
            self::notifyAllPlayers('newObjectives', clienttranslate('Bonus objective cards have been changed'), [
                'objectives' => $objectives,
            ]);
        }
    }
    
}
