<?php

trait ActionTrait {

    //////////////////////////////////////////////////////////////////////////////
    //////////// Player actions
    //////////// 
    
    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in nicodemus.action.php)
    */

    

    public function chooseCard(int $id) {
        //self::checkAction('chooseCard');

        $playerId = intval($this->getCurrentPlayerId());

        $playerHand = $this->getCardsByLocation('hand', $playerId);

        if (!$this->array_some($playerHand, fn($card) => $card->id == $id)) {
            throw new BgaUserException("You must choose a card in your hand");
        }

        $this->setPlayerSelectedCard($playerId, $id);
        $this->notifyAllPlayers('selectedCard', '', [
            'playerId' => $playerId,
            'selected' => true,
        ]);

        $this->gamestate->setPlayerNonMultiactive($playerId, 'end');
    }

    public function cancelChooseCard() {
        $playerId = intval($this->getCurrentPlayerId());

        $this->setPlayerSelectedCard($playerId, null);
        $this->notifyAllPlayers('selectedCard', '', [
            'playerId' => $playerId,
            'selected' => false,
        ]);

        $this->gamestate->setPlayersMultiactive([$playerId], 'end', false);
    }
}
