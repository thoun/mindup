class TableCenter {
    public tableOver: SlotStock<Card>;
    public tableUnder: SlotStock<Card>;

    constructor(private game: MindUpGame, gamedatas: MindUpGamedatas) {
        const playerCount = gamedatas.playerorder.length;

        let html = `
            <div id="table-over" class="table-line"></div>
            <div id="table-under" class="table-line"></div>
        `;
        document.getElementById(`table-center`).insertAdjacentHTML('beforeend', html);

        const slotSettings = {
            slotsIds: [],
            mapCardToSlot: card => card.locationArg,
        };
        for (let i=0; i<playerCount; i++) {
            slotSettings.slotsIds.push(i);
        }

        this.tableOver = new SlotStock<Card>(this.game.cardsManager, document.getElementById(`table-over`), slotSettings);
        this.tableUnder = new SlotStock<Card>(this.game.cardsManager, document.getElementById(`table-under`), slotSettings);

        this.tableOver.addCards(gamedatas.table);
    }
    
    public placeCardUnder(card: Card, playerId: number) {
        this.tableUnder.addCard(card, {
            fromElement: document.getElementById(`player-table-${playerId}`),
        });
    }
    
    public moveTableLine() {
        this.tableOver.addCards(this.tableUnder.getCards());
    }
}