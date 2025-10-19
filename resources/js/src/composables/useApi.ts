import axios from 'axios';

export const api = axios.create({ baseURL: '/api' });

export async function apiCreateGame(name: string) {
    const { data } = await api.post('/game/create', { name });
    return data as { game_code: string; game_id: number; player_id: number };
}

export async function apiJoinGame(name: string, code: string) {
    const { data } = await api.post('/game/join', { name, code });
    return data as { game_id: number; player_id: number };
}

export async function apiPlaceShips(player_id: number, ships: any) {
    const { data } = await api.post('/game/place-ships', { player_id, ships });
    return data as { started: boolean };
}

export async function apiShoot(player_id: number, x: number, y: number) {
    const { data } = await api.post('/game/shoot', { player_id, x, y });
    return data as { result: 'hit' | 'miss' | 'sunk' };
}
