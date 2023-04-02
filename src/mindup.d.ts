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
    hand?: Card[];
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
    firstPlayerId: number;
    table: Card[];
}

interface MindUpGame extends Game {
    cardsManager: CardsManager;

    getPlayerId(): number;
    getPlayerColor(playerId: number): string;

    setTooltip(id: string, html: string): void;
    onHandCardClick(card: Card): void;
    onLineCardClick(card: Card): void;
    onMarketCardClick(card: Card): void;
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

// newMarket
interface NotifNewMarketArgs {
    cards: Card[];
    deck: number;
}

// chooseMarketCardHand
interface NotifChooseMarketCardHandArgs {
    playerId: number;
    card: Card;
}

// jackpotRemaining, discardRemaining
interface NotifJackpotRemainingArgs {
    color: number;
    card: Card;
}
// newFirstPlayer
interface NotifNewFirstPlayerArgs {
    playerId: number;
}  

// playCard
interface NotifPlayCardArgs {
    playerId: number;
    card: Card;
    fromHand: boolean;
} 

// applyJackpot
interface NotifApplyJackpotArgs {
    playerId: number;
    color: number;
    count: number | string;
}

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
}