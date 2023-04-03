<?php

require_once(__DIR__.'/../constants.inc.php');


class Card {

    public int $id;
    public string $location;
    public int $locationArg;
    public /*int|null*/ $number;
    public /*int|null*/ $color;
    public /*int|null*/ $points;
    public /*int|null*/ $playerId;

    public function __construct($dbCard) {
        $CARD_COLORS = [
            1 => ORANGE,
            2 => PINK,
            3 => BLUE,
            4 => GREEN,
            5 => GREEN,
            6 => PINK,
            7 => PURPLE,
            8 => ORANGE,
            9 => PURPLE,
            10 => PINK,
          
            11 => PURPLE,
            12 => GREEN,
            13 => BLUE,
            14 => GREEN,
            15 => ORANGE,
            16 => ORANGE,
            17 => GREEN,
            18 => BLUE,
            19 => PINK,
            20 => PURPLE,
          
            21 => BLUE,
            22 => PURPLE,
            23 => ORANGE,
            24 => BLUE,
            25 => GREEN,
            26 => PURPLE,
            27 => PINK,
            28 => BLUE,
            29 => PINK,
            30 => ORANGE,
          
            31 => ORANGE,
            32 => PINK,
            33 => BLUE,
            34 => PINK,
            35 => PURPLE,
            36 => GREEN,
            37 => BLUE,
            38 => ORANGE,
            39 => PURPLE,
            40 => BLUE,
          
            41 => PURPLE,
            42 => PINK,
            43 => BLUE,
            44 => GREEN,
            45 => ORANGE,
            46 => ORANGE,
            47 => GREEN,
            48 => BLUE,
            49 => GREEN,
            50 => PURPLE,
          
            51 => PINK,
            52 => PURPLE,
            53 => ORANGE,
            54 => PURPLE,
            55 => PINK,
            56 => GREEN,
            57 => GREEN,
            58 => BLUE,
            59 => PINK,
            60 => ORANGE,
        ];

        $POINTS = [-1, 0, 1, 2, 1, 0];

        $this->id = intval($dbCard['card_id'] ?? $dbCard['id']);
        $this->location = $dbCard['card_location'] ?? $dbCard['location'];
        $this->locationArg = intval($dbCard['card_location_arg'] ?? $dbCard['location_arg']);
        $this->number = array_key_exists('card_type_arg', $dbCard) || array_key_exists('type_arg', $dbCard) ? intval($dbCard['card_type_arg'] ?? $dbCard['type_arg']) : null;
        $this->color = $this->number ? $CARD_COLORS[$this->number] : null;
        $this->points = $this->number ? $POINTS[($this->number - ($this->number >= 31 ? 0 : 1)) % 6] : null;
    } 

    public static function onlyId(Card $card) {
        return new Card([
            'card_id' => $card->id,
            'card_location' => $card->location,
            'card_location_arg' => $card->locationArg,
        ], null);
    }

    public static function onlyIds(array $cards) {
        return array_map(fn($card) => self::onlyId($card), $cards);
    }
}

?>