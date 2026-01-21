import re

file_path = '/home/ozcar/msg-ninesys/controllers/whatsappController.js'

with open(file_path, 'r') as f:
    content = f.read()

# 1. Patch QR Limit Block
qr_limit_search = r"""clients\[companyId\]\.qrAttempts = 0; // Reset para próximo ciclo"""
qr_limit_replace = r"""clients[companyId].qrAttempts = 0; // Reset para próximo ciclo
                try {
                    emitToCompany(companyId, 'status', { 
                        status: 'PAUSED', 
                        message: `Demasiados intentos. Espera unos minutos.`,
                        pausedUntil: clients[companyId].pausedUntil
                    });
                } catch (e) {}"""

content = re.sub(qr_limit_search, qr_limit_replace, content)

# 2. Patch Authenticated Block
auth_search = r"""client\.on\("authenticated", \(\) => \{\n\s+console\.log\(\n\s+`Cliente de WhatsApp autenticado correctamente para \$\{companyId\}\.`\n\s+\);\n\s+// El evento 'ready' es el que confirma que está listo para mensajes\n\s+\}\);"""

auth_replace = r"""client.on("authenticated", () => {
        console.log(
            `Cliente de WhatsApp autenticado correctamente para ${companyId}.`
        );
        try {
            emitToCompany(companyId, 'status', { status: 'AUTHENTICATED', message: 'Dispositivo vinculado. Conectando...' });
        } catch (e) {}
        // El evento 'ready' es el que confirma que está listo para mensajes
    });"""

# Try simple string replace if regex fails due to whitespace
if "client.on(\"authenticated\", () =>" in content:
    # Manual approximation if regex is too strict
    pass

# Let's try to match the auth block more loosely
auth_pattern = r"(client\.on\(\"authenticated\", \(\) => \{[\s\S]*?console\.log\([\s\S]*?\);)(\s+// El evento 'ready')"
auth_replacement = r"\1\n        try { emitToCompany(companyId, 'status', { status: 'AUTHENTICATED', message: 'Dispositivo vinculado. Conectando...' }); } catch (e) {}\2"

content = re.sub(auth_pattern, auth_replacement, content)

with open(file_path, 'w') as f:
    f.write(content)

print("Patched whatsappController.js events.")
