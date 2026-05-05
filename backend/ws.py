import asyncio
import websockets
import json
import os
from dotenv import load_dotenv

# Memuat konfigurasi dari .env
load_dotenv()

PORT = int(os.getenv("WS_PORT", 8080))
CONNECTED_CLIENTS = set()

async def handle_connection(websocket, path):
    # Registrasi klien baru
    CONNECTED_CLIENTS.add(websocket)
    try:
        async for message in websocket:
            # Logika broadcast pesan ke semua klien yang terhubung
            data = json.loads(message)
            if CONNECTED_CLIENTS:
                # Mengirim pesan kembali ke semua orang (Broadcast)
                await asyncio.wait([client.send(json.dumps(data)) for client in CONNECTED_CLIENTS])
    except websockets.exceptions.ConnectionClosed:
        pass
    finally:
        # Hapus klien saat diskoneksi
        CONNECTED_CLIENTS.remove(websocket)

async def main():
    print(f"WebSocket Server berjalan di port {PORT}...")
    async with websockets.serve(handle_connection, "0.0.0.0", PORT):
        await asyncio.Future()  # Berjalan selamanya

if __name__ == "__main__":
    asyncio.run(main())
