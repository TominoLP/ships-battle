export type Dir = 'H' | 'V';

export type AuthUser = {
  id: number;
  name: string;
  email: string;
};

export type ShipSpec = {
  name: string;
  size: number;
  count: number;
};

export type PlacedShip = {
  x: number;
  y: number;
  size: number;
  dir: Dir;
};

export type Step = 'join' | 'lobby' | 'placing' | 'playing';

// API DTOs (adjust to your backend if needed)
export type CreateGameResponse = {
  game_code: string;
  game_id: number;
  player_id: number;
};
export type JoinGameResponse = {
  game_id: number;
  player_id: number;
};
export type PlaceShipsResponse = {
  started: boolean;
};
export type ShootResponse = {
  result: 'hit' | 'miss' | 'sunk' | string;
};
