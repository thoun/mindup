declare const define;
declare const ebg;
declare const $;
declare const dojo: Dojo;
declare const _;
declare const g_gamethemeurl;

const ANIMATION_MS = 500;
const ACTION_TIMER_DURATION = 5;

const LOCAL_STORAGE_ZOOM_KEY = 'MindUp-zoom';

class MindUp implements MindUpGame {
    public cardsManager: CardsManager;

    private zoomManager: ZoomManager;
    private animationManager: AnimationManager;
    private gamedatas: MindUpGamedatas;
    private tableCenter: TableCenter;
    private playersTables: PlayerTable[] = [];
    private handCounters: Counter[] = [];
    private scoredCounters: Counter[] = [];
    private selectedCardId: number;
    
    private TOOLTIP_DELAY = document.body.classList.contains('touch-device') ? 1500 : undefined;

    constructor() {
    }
    
    /*
        setup:

        This method must set up the game user interface according to current game situation specified
        in parameters.

        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)

        "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
    */

    public setup(gamedatas: MindUpGamedatas) {
        log( "Starting game setup" );
        
        this.gamedatas = gamedatas;

        log('gamedatas', gamedatas);

        this.cardsManager = new CardsManager(this);
        this.animationManager = new AnimationManager(this);
        this.tableCenter = new TableCenter(this, gamedatas);
        this.createPlayerTables(gamedatas);
        
        this.zoomManager = new ZoomManager({
            element: document.getElementById('table'),
            smooth: false,
            zoomControls: {
                color: 'white',
            },
            localStorageZoomKey: LOCAL_STORAGE_ZOOM_KEY,
        });

        this.setupNotifications();
        this.setupPreferences();

        log( "Ending game setup" );
    }

    ///////////////////////////////////////////////////
    //// Game & client states

    // onEnteringState: this method is called each time we are entering into a new game state.
    //                  You can use this method to perform some user interface changes at this moment.
    //
    public onEnteringState(stateName: string, args: any) {
        log('Entering state: ' + stateName, args.args);

        switch (stateName) {
            case 'chooseCard':
                this.getCurrentPlayerTable()?.setSelectable(true);
                break;
        }
    }

    public onLeavingState(stateName: string) {
        log( 'Leaving state: '+stateName );

        switch (stateName) {
           case 'chooseCard':
                this.getCurrentPlayerTable()?.setSelectable(false);
                break;
        }
    }

    // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
    //                        action status bar (ie: the HTML links in the status bar).
    //
    public onUpdateActionButtons(stateName: string, args: any) {
        if (stateName === 'chooseCard') {
            if (!(this as any).isCurrentPlayerActive() && Object.keys(this.gamedatas.players).includes(''+this.getPlayerId())) { // ignore spectators
                (this as any).addActionButton(`cancelChooseSecretMissions-button`, _("I changed my mind"), () => this.cancelChooseCard(), null, null, 'gray');
            }
        }
    }

    ///////////////////////////////////////////////////
    //// Utility methods


    ///////////////////////////////////////////////////

    public setTooltip(id: string, html: string) {
        (this as any).addTooltipHtml(id, html, this.TOOLTIP_DELAY);
    }
    public setTooltipToClass(className: string, html: string) {
        (this as any).addTooltipHtmlToClass(className, html, this.TOOLTIP_DELAY);
    }

    public getPlayerId(): number {
        return Number((this as any).player_id);
    }

    public getPlayerColor(playerId: number): string {
        return this.gamedatas.players[playerId].color;
    }

    private getPlayer(playerId: number): MindUpPlayer {
        return Object.values(this.gamedatas.players).find(player => Number(player.id) == playerId);
    }

    private getPlayerTable(playerId: number): PlayerTable {
        return this.playersTables.find(playerTable => playerTable.playerId === playerId);
    }

    private getCurrentPlayerTable(): PlayerTable | null {
        return this.playersTables.find(playerTable => playerTable.playerId === this.getPlayerId());
    }

    private setupPreferences() {
        // Extract the ID and value from the UI control
        const onchange = (e) => {
          var match = e.target.id.match(/^preference_[cf]ontrol_(\d+)$/);
          if (!match) {
            return;
          }
          var prefId = +match[1];
          var prefValue = +e.target.value;
          (this as any).prefs[prefId].value = prefValue;
        }
        
        // Call onPreferenceChange() when any value changes
        dojo.query(".preference_control").connect("onchange", onchange);
        
        // Call onPreferenceChange() now
        dojo.forEach(
          dojo.query("#ingame_menu_content .preference_control"),
          el => onchange({ target: el })
        );
    }

    private getOrderedPlayers(gamedatas: MindUpGamedatas) {
        const players = Object.values(gamedatas.players).sort((a, b) => a.playerNo - b.playerNo);
        const playerIndex = players.findIndex(player => Number(player.id) === Number((this as any).player_id));
        const orderedPlayers = playerIndex > 0 ? [...players.slice(playerIndex), ...players.slice(0, playerIndex)] : players;
        return orderedPlayers;
    }

    private createPlayerTables(gamedatas: MindUpGamedatas) {
        const orderedPlayers = this.getOrderedPlayers(gamedatas);

        orderedPlayers.forEach(player => 
            this.createPlayerTable(gamedatas, Number(player.id))
        );
    }

    private createPlayerTable(gamedatas: MindUpGamedatas, playerId: number) {
        const table = new PlayerTable(this, gamedatas.players[playerId], gamedatas.costs);
        this.playersTables.push(table);
    }

    private incScore(playerId: number, inc: number) {
        (this as any).scoreCtrl[playerId]?.incValue(inc);
    }

    private incScored(playerId: number, inc: number) {
        this.scoredCounters[playerId].incValue(inc);
        this.incScore(playerId, inc);
    }

    public onHandCardClick(card: Card): void {
        this.chooseCard(card.id);
    }
  	
    public chooseCard(id: number) {
        /*if(!(this as any).checkAction('chooseCard')) {
            return;
        }*/

        this.takeAction('chooseCard', {
            id
        });
    }
  	
    public cancelChooseCard() {
        /*if(!(this as any).checkAction('cancelChooseCard')) {
            return;
        }*/

        this.takeAction('cancelChooseCard');
    }

    public takeAction(action: string, data?: any) {
        data = data || {};
        data.lock = true;
        (this as any).ajaxcall(`/mindup/mindup/${action}.html`, data, this, () => {});
    }

    ///////////////////////////////////////////////////
    //// Reaction to cometD notifications

    /*
        setupNotifications:

        In this method, you associate each of your game notifications with your local method to handle it.

        Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                your pylos.game.php file.

    */
    setupNotifications() {
        //log( 'notifications subscriptions setup' );

        const notifs = [
            ['newRound', 1],
            ['selectedCard', 1],
            ['placeCardUnder', ANIMATION_MS],
            ['scoreCard', ANIMATION_MS],
            ['moveTableLine', ANIMATION_MS],
        ];
    
        notifs.forEach((notif) => {
            dojo.subscribe(notif[0], this, `notif_${notif[0]}`);
            (this as any).notifqueue.setSynchronous(notif[0], notif[1]);
        });
    }

    notif_newRound(notif: Notif<NotifNewRoundArgs>) {
        this.playersTables.forEach(table => table.setCosts(notif.args.costs));
    }

    notif_selectedCard(notif: Notif<NotifSelectedCardArgs>) {
        console.log(notif.args);
    }

    notif_placeCardUnder(notif: Notif<NotifPlayerCardArgs>) {
        this.tableCenter.placeCardUnder(notif.args.card, notif.args.playerId);
    }

    notif_scoreCard(notif: Notif<NotifPlayerCardArgs>) {
        this.getPlayerTable(notif.args.playerId).placeScoreCard(notif.args.card);
    }

    notif_moveTableLine() {
        this.tableCenter.moveTableLine();
    }

    /*notif_applyJackpot(notif: Notif<NotifApplyJackpotArgs>) {
        this.incScored(notif.args.playerId, Number(notif.args.count));
        this.tableCenter.setJackpot(notif.args.color, 0);
        notif.args.lineColorCard.forEach(card => this.cardsManager.getCardElement(card).classList.add('jackpot-animation'));
    }

    notif_betResult(notif: Notif<NotifBetResultArgs>) {
        this.addBetToken(notif.args.playerId, notif.args.value);
        this.incScore(notif.args.playerId, Number(notif.args.value));
    }

    notif_closeLine(notif: Notif<NotifApplyJackpotArgs>) {
        this.getPlayerTable(notif.args.playerId).line.removeAll();
        this.incScored(notif.args.playerId, Number(notif.args.count));
    }*/


    /* This enable to inject translatable styled things to logs or action bar */
    /* @Override */
    public format_string_recursive(log: string, args: any) {
        try {
            if (log && args && !args.processed) {
                if (args.cardValue == '' && args.card) {
                    args.cardValue = `<strong data-color="${args.card.color}">${args.card.type == 2 && args.card.number > 0 ? '+' : ''}${args.card.number}</strong>`;
                }
                if (typeof args.colorName == 'string' && args.colorName[0] !== '<' && args.color) {
                    args.colorName = `<div class="jackpot-icon" data-color="${args.color}"></div>`;
                }
            }
        } catch (e) {
            console.error(log,args,"Exception thrown", e.stack);
        }
        return (this as any).inherited(arguments);
    }
}