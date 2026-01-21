import re

file_path = '/home/ozcar/msg-ninesys/websocket.js'

with open(file_path, 'r') as f:
    content = f.read()

# Pattern for the disconnect-client block
# We want to change the destructuring and add initializeClient call.
# Original:
#         const { disconnectClient } = require('./controllers/whatsappController');
#         await disconnectClient(companyId);
#         socket.emit('status', { status: 'DISCONNECTED', ws_ready: false, qr: null });

# Desired:
#         const { disconnectClient, initializeClient } = require('./controllers/whatsappController');
#         await disconnectClient(companyId);
#         console.log('[WS] Auto-inicializando tras desconexion para nuevo QR');
#         initializeClient(companyId);
#         // socket.emit('status', ...); // Optional, but initializeClient will emit INITIALIZING shortly after.

# Let's use string replacement if we can match unique context.
target_block = r"""        // Desconectar cliente de WhatsApp
        socket.on('disconnect-client', async (companyId) => {
            console.log(`[WS] Comando 'disconnect-client' recibido para ${companyId}`);
            try {
                const { disconnectClient } = require('./controllers/whatsappController');
                await disconnectClient(companyId);
                socket.emit('status', { status: 'DISCONNECTED', ws_ready: false, qr: null });
            } catch (error) {"""

replacement_block = r"""        // Desconectar cliente de WhatsApp
        socket.on('disconnect-client', async (companyId) => {
            console.log(`[WS] Comando 'disconnect-client' recibido para ${companyId}`);
            try {
                const { disconnectClient, initializeClient } = require('./controllers/whatsappController');
                await disconnectClient(companyId);
                console.log(`[WS] Reinicializando cliente tras desconexión para generar nuevo QR para ${companyId}`);
                initializeClient(companyId);
            } catch (error) {"""

if target_block in content:
    new_content = content.replace(target_block, replacement_block)
    with open(file_path, 'w') as f:
        f.write(new_content)
    print("Successfully patched websocket.js")
else:
    # Try fuzzy match or regex if indentation varies
    print("Could not find exact block. trying regex.")
    
    # Regex approach
    pattern = r"socket\.on\('disconnect-client'[\s\S]*?require\('\./controllers/whatsappController'\);\s*await disconnectClient\(companyId\);\s*socket\.emit\('status'[\s\S]*?\}\)[\s\S]*?\}"
    
    # This regex is risky without checking exact group structure.
    # Let's try replacing just the inner part
    
    inner_pattern = r"(const \{ disconnectClient \} = require\('\./controllers/whatsappController'\);)(\s+await disconnectClient\(companyId\);)(\s+socket\.emit\('status', \{ status: 'DISCONNECTED', ws_ready: false, qr: null \}\);)"
    
    replacement = r"const { disconnectClient, initializeClient } = require('./controllers/whatsappController');\2\n                initializeClient(companyId);"
    
    new_content_re = re.sub(inner_pattern, replacement, content)
    
    if new_content_re != content:
        with open(file_path, 'w') as f:
            f.write(new_content_re)
        print("Successfully patched websocket.js using regex.")
    else:
        print("Failed to patch websocket.js")
