const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const cors = require('cors');
const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

const app = express();
const server = http.createServer(app);
const io = new Server(server, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"]
    }
});

const PORT = 4001;

app.use(cors());
app.use(express.json());
app.use(express.static(path.join(__dirname, '../ui')));

// Cargar configuración de scripts
const scriptsPath = path.join(__dirname, 'scripts.json');
const getScripts = () => JSON.parse(fs.readFileSync(scriptsPath, 'utf8'));

app.get('/api/scripts', (req, res) => {
    res.json(getScripts());
});

io.on('connection', (socket) => {
    console.log('Cliente conectado');
    let child = null;

    socket.on('run-script', (data) => {
        const scriptId = typeof data === 'string' ? data : data.id;
        const scriptArgs = data.args || [];
        
        const scripts = getScripts();
        const script = scripts.find(s => s.id === scriptId);

        if (!script) {
            return socket.emit('error', { msg: 'Script no encontrado' });
        }

        const scriptFullPath = path.resolve(__dirname, '..', script.path);
        const command = script.type === 'python' ? 'python3' : 'bash';

        socket.emit('output', { type: 'system', msg: `Iniciando ${script.name}...` });

        // Combinar script y argumentos
        const spawnArgs = [scriptFullPath, ...scriptArgs];

        child = spawn(command, spawnArgs, {
            cwd: script.workingDir || path.resolve(__dirname, '..'),
            env: { ...process.env, TERM: 'xterm' }
        });

        child.stdout.on('data', (data) => {
            const msg = data.toString();
            console.log(`STDOUT [${scriptId}]: ${msg}`);
            socket.emit('output', { type: 'stdout', msg });
        });

        child.stderr.on('data', (data) => {
            const msg = data.toString();
            console.log(`STDERR [${scriptId}]: ${msg}`);
            socket.emit('output', { type: 'stderr', msg });
        });

        child.on('close', (code) => {
            console.log(`Script ${scriptId} finalizado con código: ${code}`);
            socket.emit('output', { type: 'system', msg: `Proceso terminado con código: ${code}` });
            socket.emit('exit', code);
            child = null;
        });
    });

    socket.on('input', (data) => {
        console.log(`CLIENT INPUT [${data}]`);
        if (child && child.stdin.writable) {
            child.stdin.write(data + '\n');
        } else {
            console.log('ADVERTENCIA: Intento de escribir en stdin no disponible');
        }
    });

    socket.on('disconnect', () => {
        if (child) {
            child.kill();
        }
        console.log('Cliente desconectado');
    });
});

server.listen(PORT, () => {
    console.log(`Ninesys Hub Server (WebSockets) running at http://localhost:${PORT}`);
});
