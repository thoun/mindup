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
    private roundCounter: Counter;
    
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
        (this as any).bga.gameArea.getElement().insertAdjacentHTML('beforeend', `
            <div id="table">
                <div id="round-counter-row">
                    <div id="round-counter-block">${_("Round number:")} <span id="round-counter"></span> / 3</div>
                </div>
                <div id="tables-and-center">
                    <div id="table-center">
                        <div id="objectives" class="card-line"></div>
                        <div id="player-cards" class="card-line label-top"></div>
                        <hr/>
                        <div id="table-over" class="card-line"></div>
                        <div id="table-under" class="card-line label-top"></div>
                    </div>
                    <div id="tables"></div>
                </div>
            </div>
        `);
        
        this.gamedatas = gamedatas;

        log('gamedatas', gamedatas);

        this.roundCounter = new ebg.counter();
        this.roundCounter.create(`round-counter`);
        this.roundCounter.setValue(gamedatas.roundNumber);


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
                (this as any).bga.statusBar.addActionButton(
                    _("I changed my mind"), 
                    () => (this as any).bga.actions.performAction('actCancelChooseCard', null, { checkAction: false }), 
                    { color: 'secondary' }
                );
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

    public getPlayer(playerId: number): MindUpPlayer {
        return Object.values(this.gamedatas.players).find(player => Number(player.id) == playerId);
    }

    private getPlayerTable(playerId: number): PlayerTable {
        return this.playersTables.find(playerTable => playerTable.playerId === playerId);
    }

    private getCurrentPlayerTable(): PlayerTable | null {
        return this.playersTables.find(playerTable => playerTable.playerId === this.getPlayerId());
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

    private setScore(playerId: number, score: number) {
        (this as any).scoreCtrl[playerId]?.toValue(score);
    }

    public onHandCardClick(card: Card): void {
        (this as any).bga.actions.performAction('actChooseCard', { id: card.id }, { checkAction: false });
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
            ['delayBeforeReveal', ANIMATION_MS],
            ['revealCards', ANIMATION_MS * 2],
            ['placeCardUnder', ANIMATION_MS],
            ['delayAfterLineUnder', ANIMATION_MS * 2],
            ['scoreCard', ANIMATION_MS * 2],
            ['moveTableLine', ANIMATION_MS],
            ['delayBeforeNewRound', ANIMATION_MS],
            ['newCard', 1],
            ['newObjectives', 1],
        ];
    
        notifs.forEach((notif) => {
            dojo.subscribe(notif[0], this, (notifDetails: Notif<any>) => {
                log(`notif_${notif[0]}`, notifDetails.args);

                const promise = this[`notif_${notif[0]}`](notifDetails.args);

                // tell the UI notification ends, if the function returned a promise
                promise?.then(() => (this as any).notifqueue.onSynchronousNotificationEnd());
            });
            (this as any).notifqueue.setSynchronous(notif[0], notif[1]);
        });

        if (isDebug) {
            notifs.forEach((notif) => {
                if (!this[`notif_${notif[0]}`]) {
                    console.warn(`notif_${notif[0]} function is not declared, but listed in setupNotifications`);
                }
            });

            Object.getOwnPropertyNames(MindUp.prototype).filter(item => item.startsWith('notif_')).map(item => item.slice(6)).forEach(item => {
                if (!notifs.some(notif => notif[0] == item)) {
                    console.warn(`notif_${item} function is declared, but not listed in setupNotifications`);
                }
            });
        }
    }

    notif_newRound(args: NotifNewRoundArgs) {
        this.roundCounter.toValue(args.number);
        this.playersTables.forEach(table => table.newRound(args.costs));
    }

    notif_selectedCard(args: NotifSelectedCardArgs) {
        const currentPlayer = this.getPlayerId() == args.playerId;
        if (args.card.number || !currentPlayer) {
            if (args.cancel) {
                if (currentPlayer) {
                    this.getCurrentPlayerTable().hand.addCard(args.card);
                } else {
                    this.tableCenter.cancelPlacedCard(args.card);
                }
            } else {
                this.tableCenter.setPlacedCard(args.card, currentPlayer);
            }
        }
    }

    notif_delayBeforeReveal() {}

    notif_revealCards(args: NotifRevealCardsArgs) {
        this.tableCenter.revealCards(args.cards);
    }

    notif_placeCardUnder(args: NotifPlayerCardArgs) {
        this.tableCenter.placeCardUnder(args.playerId, args.card);
    }
    
    notif_delayAfterLineUnder() {}

    notif_scoreCard(args: NotifScoredCardArgs) {
        this.getPlayerTable(args.playerId).placeScoreCard(args.card);

        this.setScore(args.playerId, args.playerScore);
    }

    notif_moveTableLine() {
        this.tableCenter.moveTableLine();
    }

    notif_delayBeforeNewRound() {}

    notif_newCard(args: NotifPlayerCardArgs) {
        this.getCurrentPlayerTable().hand.addCard(args.card);
    }

    notif_newObjectives(args: NotifNewObjectivesArgs) {
        this.tableCenter.changeObjectives(args.objectives);
    }

    /* This enable to inject translatable styled things to logs or action bar */
    /* @Override */
    public format_string_recursive(log: string, args: any) {
        try {
            if (log && args && !args.processed) {

                ['scoredCard', 'cardOver', 'cardUnder', 'addedCard'].forEach(attr => {
                    if ((typeof args[attr] !== 'string' || args[attr][0] !== '<') && args[attr + 'Obj']) {
                        const obj: Card = args[attr + 'Obj'];
                        args[attr] = `<strong data-color="${obj.color}">${obj.number}</strong>`;
                        if (obj.points != 0) {
                            args[attr] += ` <div class="points-circle" data-negative="${(obj.points < 0).toString()}">${obj.points > 0 ? '+' : ''}${obj.points}</div>`;
                        }
                    }
                });

                for (const property in args) {
                    if (['column', 'incScoreColumn', 'incScoreCard', 'roundNumber', 'totalScore', 'roundScore'].includes(property) && args[property][0] != '<') {
                        args[property] = `<strong>${_(args[property])}</strong>`;
                    }
                }
                
            }
        } catch (e) {
            console.error(log,args,"Exception thrown", e.stack);
        }
        return (this as any).inherited(arguments);
    }
}