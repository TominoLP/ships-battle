export type Step = 'join' | 'lobby' | 'placing' | 'playing';

export type ShipSpec = { name: string; size: number; count: number };
export type Dir = 'H' | 'V';
export type PlacedShip = { x: number; y: number; size: number; dir: Dir };

export type SocketEventMap = {
    game_finished: { winner: { id: number; name: string } };
    player_joined: { player: { id: number; name: string } };
    player_ready: { player: { id: number; name: string } };
    turn_changed: { player: { id: number; name: string } };
    shot_fired: { player: { id: number; name: string }; x: number; y: number; result: 'hit' | 'miss' | 'sunk' };
    game_started: { current?: { id: number; name: string } };
};
