import re

file_path = '/home/ozcar/msg-ninesys/controllers/whatsappController.js'

new_function = r"""const disconnectClient = async (companyId) => {
    console.log(`[Internal] Solicitud de desconexión robusta para companyId: ${companyId}`);

    // [Fix 1] Actualizar estado en memoria INMEDIATAMENTE
    if (clients[companyId]) {
        clients[companyId].whatsappReady = false;
        clients[companyId].qrCodeImage = null;
    }

    try {
        if (clients[companyId] && clients[companyId].client) {
            console.log(`Ejecutando logout para ${companyId} con timeout...`);
            
            // [Fix 2] Timeout para logout (5s)
            const logoutPromise = clients[companyId].client.logout();
            const timeoutPromise = new Promise((_, reject) => setTimeout(() => reject(new Error('Logout timed out')), 5000));

            try {
                await Promise.race([logoutPromise, timeoutPromise]);
            } catch (e) {
                console.warn(`Logout fallido o timeout para ${companyId}. Forzando destrucción.`, e);
                try { await clients[companyId].client.destroy(); } catch (err) {}
            }
        } 
        
        // [Fix 3] Limpieza forzada de archivos y memoria SIEMPRE
        console.log(`Limpiando archivos de sesión y memoria para ${companyId}...`);
        const sessionFolderPath = path.join(__dirname, '..', '.wwebjs_auth', `session-${companyId}`);
        try {
            await fs.rm(sessionFolderPath, { recursive: true, force: true });
        } catch(e) { console.error("Error borrando carpeta:", e); }
        
        if (clients[companyId]) {
            delete clients[companyId]; 
        }

        return true;
    } catch (error) {
        console.error(`Error crítico en disconnectClient para ${companyId}:`, error);
        return false;
    }
};"""

with open(file_path, 'r') as f:
    content = f.read()

# Pattern to find the exact function block. 
# We look for "const disconnectClient = async (companyId) => {" until the next "module.exports" or closing brace
# But simple regex replacement might be safer if we match the start and approximate end
pattern = r"const disconnectClient = async \(companyId\) => \{[\s\S]*?^};"

# Check if we can find it
match = re.search(pattern, content, re.MULTILINE)
if match:
    # Replace
    new_content = content.replace(match.group(0), new_function)
    with open(file_path, 'w') as f:
        f.write(new_content)
    print("Successfully replaced disconnectClient function.")
else:
    print("Could not find disconnectClient function to replace.")
