<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Monitor del servidor en tiempo real</title>
    @vite('resources/js/app.js')
    <style>
        body {
            font-family: system-ui, sans-serif;
            background: #f4f5f7;
            margin: 0;
            padding: 24px;
        }

        h1 {
            margin-top: 0;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }

        .card {
            background: #fff;
            border-radius: 8px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .1);
        }

        .label {
            color: #666;
            font-size: 14px;
        }

        .value {
            font-size: 24px;
            font-weight: 600;
            margin-top: 4px;
        }

        #estado {
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 12px;
        }

        .on {
            background: #e7f6ec;
            color: #15803d;
        }

        .off {
            background: #fdecec;
            color: #b91c1c;
        }

        .acciones {
            margin: 24px 0;
            display: flex;
            gap: 12px;
        }

        .btn {
            background: #1d5fa8;
            color: #fff;
            border: 0;
            border-radius: 8px;
            padding: 12px 20px;
            font-size: 16px;
            cursor: pointer;
        }

        .btn:hover {
            background: #12305a;
        }

        #actividad {
            background: #fff;
            border-radius: 8px;
            padding: 12px 32px;
            min-height: 24px;
        }
    </style>
</head>

<body>
    <h1>Monitor del servidor</h1>
    <p><a href="/graficos">Ver gráficos en tiempo real →</a></p>
    <p>
        WebSocket: <span id="estado" class="off">Conectando...</span>
        · Última lectura: <span id="at">{{ $data['at'] }}</span>
    </p>
    <div class="grid">
        <div class="card">
            <div class="label">Nombre del equipo</div>
            <div class="value">{{ $data['hostname'] }}</div>
        </div>
        <div class="card">
            <div class="label">Sistema operativo</div>
            <div class="value">{{ $data['os'] }}</div>
        </div>
        <div class="card">
            <div class="label">Disco usado (%)</div>
            <div class="value" id="disk">{{ $data['disk']['used_pct'] }} %</div>
        </div>
        <div class="card">
            <div class="label">Memoria usada (%)</div>
            <div class="value" id="memory">{{ isset($data['memory']['used_pct']) ? $data['memory']['used_pct'] . ' %' : 'No disponible' }}</div>
        </div>
        <div class="card">
            <div class="label">Uso de CPU (%)</div>
            <div class="value" id="cpu">{{ isset($data['cpu_pct']) ? $data['cpu_pct'] . ' %' : 'No disponible' }}</div>
        </div>
        <div class="card">
            <div class="label">Temperatura CPU (°C)</div>
            <div class="value" id="temp">{{ isset($data['cpu_temp_c']) ? $data['cpu_temp_c'] . ' °C' : 'No disponible' }}</div>
        </div>
        <div class="card">
            <div class="label">Encendido hace (segundos)</div>
            <div class="value" id="uptime">{{ $data['uptime_s'] ?? 'No disponible' }}</div>
        </div>
    </div>
    @if (config('app.demo_apps'))
        <div class="acciones">
            <button class="btn" data-app="calculadora">Abrir calculadora</button>
            <button class="btn" data-app="bloc">Abrir Bloc de notas</button>
        </div>
    @endif
    <h2>Actividad en vivo</h2>
    <ul id="actividad"></ul>
    <!-- En el Paso 5.2 va el script acá -->
    <script type="module">
        const set = (id, valor) => {
            document.getElementById(id).textContent = valor ?? 'No disponible';
        };
        const setPct = (id, valor) => {
            const n = Number(valor);
            document.getElementById(id).textContent =
                valor === null || valor === undefined || Number.isNaN(n) ?
                'No disponible' :
                `${Number.isInteger(n) ? n : n.toFixed(1)} %`;
        };
        const setTemp = (id, valor) => {
            const n = Number(valor);
            document.getElementById(id).textContent =
                valor === null || valor === undefined || Number.isNaN(n) ?
                'No disponible' :
                `${n.toFixed(1)} °C`;
        };
        // 1. Métricas: llegan solas cada 5 segundos
        function pintarMetricas(d) {
            set('at', d.at);
            setPct('disk', d.disk?.used_pct);
            setPct('memory', d.memory?.used_pct);
            setPct('cpu', d.cpu_pct);
            setTemp('temp', d.cpu_temp_c);
            set('uptime', d.uptime_s);
        }
        let websocketConectado = false;
        if (window.Echo) {
            window.Echo.channel('server.metrics')
                .listen('MetricsUpdated', (e) => pintarMetricas(e.data));
            // 2. Actividad: alguien abrió un programa en el servidor
            window.Echo.channel('server.demo')
                .listen('AppOpened', (e) => {
                    const li = document.createElement('li');
                    li.textContent = `${e.at} · ${e.ip} abrió ${e.app}`;
                    document.getElementById('actividad').prepend(li);
                });
        }
        // 3. Estado de la conexión WebSocket
        const estado = document.getElementById('estado');
        const pintar = (actual) => {
            const ok = actual === 'connected';
            websocketConectado = ok;
            estado.textContent = ok ? 'Conectado' : 'Sin conexión';
            estado.className = ok ? 'on' : 'off';
        };
        if (window.Echo) {
            const conexion = window.Echo.connector.pusher.connection;
            pintar(conexion.state);
            conexion.bind('state_change', ({
                current
            }) => pintar(current));
        } else {
            pintar('unavailable');
        }
        // 4. Respaldo: si el WebSocket no está conectado, consultar la API
        async function cargarPorHttp() {
            try {
                const res = await fetch('/api/metrics', {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                if (res.ok) pintarMetricas(await res.json());
            } catch (error) {
                console.error('No se pudieron leer las métricas:', error);
            }
        }
        cargarPorHttp();
        setInterval(() => {
            if (!websocketConectado) cargarPorHttp();
        }, 5000);
        // 4. Botones: piden al servidor que abra un programa
        async function abrir(app) {
            const token = document.querySelector('meta[name="csrf-token"]').content;
            const res = await fetch(`/demo/abrir/${app}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
            });
            if (res.status === 429) alert('Esperá un minuto antes de volver a probar.');
            if (res.status === 403) alert('La demo está desactivada.');
        }
        document.querySelectorAll('[data-app]').forEach((boton) => {
            boton.addEventListener('click', () => abrir(boton.dataset.app));
        });
    </script>
</body>

</html>
