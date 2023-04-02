/**
 * Your game interfaces
 */

interface Card {
    id: number;
    location: string;
    locationArg: number;
    type: number;
    color: number;
    number: number;
}

interface MindUpPlayer extends Player {
    playerNo: number;
    scoresCards: Card[][];
    hand?: Card[];
    selectedCard?: number;
}

interface MindUpGamedatas {
    current_player_id: string;
    decision: {decision_type: string};
    game_result_neutralized: string;
    gamestate: Gamestate;
    gamestates: { [gamestateId: number]: Gamestate };
    neutralized_player_id: string;
    notifications: {last_packet_id: string, move_nbr: string}
    playerorder: (string | number)[];
    players: { [playerId: number]: MindUpPlayer };
    tablespeed: string;

    // Add here variables you set up in getAllDatas
    table: Card[];
    costs: number[];
}

interface MindUpGame extends Game {
    cardsManager: CardsManager;

    getPlayerId(): number;
    getPlayerColor(playerId: number): string;

    setTooltip(id: string, html: string): void;
    onHandCardClick(card: Card): void;
}

interface EnteringChooseMarketCardArgs {
    canPlaceOnLine: Card[];
    canAddToLine: boolean;
    canAddToHand: boolean;
    mustClose: boolean;
    canClose: boolean;
}

interface EnteringPlayCardArgs {
    canPlaceOnLine: Card[];
    canClose: boolean;
    onlyClose: boolean;
}

interface EnteringPlayHandCardArgs {
    canPlaceOnLine: Card[];
}

// newRound
interface NotifNewRoundArgs {
    costs: number[];
}

// selectedCard
interface NotifSelectedCardArgs {
    playerId: number;
    selected: boolean;
} 

// placeCardUnder
interface NotifPlayerCardArgs {
    card: Card;
    playerId: number;
}
/*
// betResult
interface NotifBetResultArgs {
    playerId: number;
    value: number;
}

// closeLine
interface NotifApplyJackpotArgs {
    playerId: number;
    count: number | string;
    removed: number | string;
    color: number;
    lineColorCard: Card[];
}*/