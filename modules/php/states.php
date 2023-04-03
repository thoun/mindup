<?php

trait StateTrait {

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

    function stNewRound() {
        $this->DbQuery("UPDATE player SET `player_score_aux` = 0");

        $affectedCosts = [];
        $costs = [1, 2, 3, 4, 5];

        for ($i = 0; $i < 5; $i++) {
            $index = bga_rand(1, count($costs)) - 1;
            $affectedCosts[] = $costs[$index];
            array_splice($costs, $index, 1);
        }
        $this->setGlobalVariable(COSTS, $affectedCosts);

        self::notifyAllPlayers('newRound', '', [
            'costs' => $affectedCosts,
        ]);

        $this->gamestate->nextState('next');
    }

    function stChooseCard() {
        $this->gamestate->setAllPlayersMultiactive();
    }

    function stRevealCards() {
        $tableUnder = $this->getCardsByLocation('selected');

        self::notifyAllPlayers('delayBeforeReveal', '', []);
        self::notifyAllPlayers('revealCards', '', [
            'cards' => json_decode(json_encode($tableUnder)),
        ]);

        foreach($tableUnder as &$card) {
            $card->playerId = $card->locationArg;
        }
        $table = $this->getCardsByLocation('table');

        usort($tableUnder, fn($a, $b) => $a->number - $b->number);
        foreach ($tableUnder as $index => $cardUnder) {
            $this->cards->moveCard($cardUnder->id, 'tableUnder', $index);
            $cardUnder->locationArg = $index;
            $logCardUnder = json_decode(json_encode($cardUnder));
            $logCardOver = $table[$index];

            self::notifyAllPlayers('placeCardUnder', clienttranslate('${player_name} places card ${cardUnder} under ${cardOver} at column ${column}'), [
                'card' => $logCardUnder,
                'playerId' => $cardUnder->playerId,
                'player_name' => $this->getPlayerName($cardUnder->playerId),
                'cardOver' => $logCardOver->number,
                'cardUnder' => $logCardUnder->number,
                'cardOverObj' => $logCardOver,
                'cardUnderObj' => $logCardUnder,
                'column' => $index + 1,
                'preserve' => ['cardOverObj', 'cardUnderObj'],
            ]);
        }

        $costs = $this->getGlobalVariable(COSTS, true);

        foreach ($tableUnder as $index => $cardUnder) {
            $cardOver = $table[$index];
            $col = $this->getCol($cardUnder->playerId, $cardOver->color);
            $this->cards->moveCard($cardOver->id, 'score'.$cardUnder->playerId, $col);
            $cardOver->locationArg = $col;
            $logCard = json_decode(json_encode($cardOver));

            $playerScore = $this->updatePlayerScore($cardUnder->playerId, $costs);

            self::notifyAllPlayers('scoreCard', clienttranslate('${player_name} adds card ${scoredCard} to its score column ${column} and scores ${incScoreColumn} points for score card and ${incScoreCard} points for added card points'), [
                'playerId' => $cardUnder->playerId,
                'player_name' => $this->getPlayerName($cardUnder->playerId),
                'card' => $logCard,
                'scoredCard' => $logCard->number,
                'scoredCardObj' => $logCard,
                'incScoreColumn' => $costs[$col],
                'incScoreCard' => $logCard->points,
                'column' => $col + 1,
                'playerScore' => $playerScore,
                'incScore' => $logCard->points,
                'preserve' => ['scoredCardObj'],
            ]);
        }

        $this->cards->moveAllCardsInLocationKeepOrder('tableUnder', 'table');
        self::notifyAllPlayers('moveTableLine', '', []);

        $this->gamestate->nextState('next');
    }

    function stEndRound() {
        $this->incStat(1, 'roundNumber');

        $lastRound = $this->getStat('roundNumber') >= 3;

        // apply round score (scoreAux) to score
        $this->DbQuery("UPDATE player SET `player_score` = `player_score` + `player_score_aux`");

        $this->gamestate->nextState($lastRound ? 'endDeck' : 'newRound');
    }

    function stEndScore() {
        $playersIds = $this->getPlayersIds();

        foreach($playersIds as $playerId) {
            // TODO stats?
        }

        $this->gamestate->nextState('endGame');
    }
}
