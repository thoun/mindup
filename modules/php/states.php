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
        $dbResults = $this->getCollectionFromDb("SELECT `player_id`, `player_selected_card` FROM `player`");
        $selectedCardsIds = array_map(fn($dbResult) => intval($dbResult['player_selected_card']), $dbResults);

        $tableUnder = [];
        foreach($selectedCardsIds as $playerId => $id) {
            $card = $this->getCardById($id);
            $card->playerId = $playerId;
            $tableUnder[] = $card;
        }

        usort($tableUnder, fn($a, $b) => $a->number - $b->number);
        foreach ($tableUnder as $index => $cardUnder) {
            $this->cards->moveCard($cardUnder->id, 'tableUnder', $index);
            $cardUnder->locationArg = $index;

            self::notifyAllPlayers('placeCardUnder', '', [
                'card' => json_decode(json_encode($cardUnder)),
                'playerId' => $cardUnder->playerId,
            ]);
        }

        $table = $this->getCardsByLocation('table');

        foreach ($tableUnder as $index => $cardUnder) {
            $cardOver = $table[$index];
            $col = $this->getCol($cardUnder->playerId, $cardOver->color);
            $this->cards->moveCard($cardOver->id, 'score'.$cardUnder->playerId, $col);
            $cardOver->locationArg = $col;

            self::notifyAllPlayers('scoreCard', '', [
                'playerId' => $cardUnder->playerId,
                'card' => json_decode(json_encode($cardOver)),
            ]);
        }

        $this->cards->moveAllCardsInLocationKeepOrder('tableUnder', 'table');
        self::notifyAllPlayers('moveTableLine', '', [
        ]);

        $this->gamestate->nextState('next');
    }

    function stEndRound() {
        $this->incStat(1, 'roundNumber');

        $cards = $this->getCardsByLocation('market');
        foreach($cards as $card) {
            if ($card->type == 1) {
                $this->cards->moveCard($card->id, 'jackpot', $card->color);
            
                self::notifyAllPlayers('jackpotRemaining', clienttranslate('Card ${cardValue} is added to the jackpot ${colorName}'), [
                    'colorName' => $this->getColorName($card->color),
                    'color' => $card->color,
                    'card' => $card,
                    'cardValue' => '',
                    'preserve' => ['color', 'colorName'],
                ]);
            } else if ($card->type == 2) {
                $this->cards->moveCard($card->id, 'discard');
            
                self::notifyAllPlayers('discardRemaining', clienttranslate('Card ${cardValue} is discarded'), [
                    'card' => $card,
                    'cardValue' => '',
                    'preserve' => ['card', 'cardValue'],
                ]);
            }
        }

        $lastRound = intval($this->cards->countCardInLocation('deck')) < $this->getRoundCardCount();

        $this->gamestate->nextState($lastRound ? 'endDeck' : 'newRound');
    }

    function stEndScore() {
        $playersIds = $this->getPlayersIds();

        foreach($playersIds as $playerId) {
            $this->applyCloseLine($playerId);
        }

    
    /*foreach($playersIds as $playerId) {
        $scoredCards = intval($this->cards->countCardInLocation('scored'));
    }
        /*
Chaque carte Numéro dans votre pile de score rapporte 1 point, auquel vous ajoutez
les bonus ou malus de vos jetons Pari.



    function setPlayerScore(int $playerId, int $score) {
        $this->DbQuery("UPDATE player SET `player_score` = $score WHERE player_id = $playerId");
            
        $this->notifyAllPlayers('score', clienttranslate('${player_name} ends ${cardValue} to line'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'score' => $score,
        ]);
    }

Le joueur ayant le meilleur score remporte la partie !
En cas d'égalité, la victoire est partagée.*/

        $this->gamestate->nextState('endGame');
    }
}
