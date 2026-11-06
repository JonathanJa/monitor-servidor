<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gráficos en tiempo real</title>
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

        a {
            color: #1d5fa8;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 16px;
        }

        .card {
            background: #fff;
            border-radius: 8px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .1);
        }

        .grafico-head {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 8px;
        }

        .label {
            color: #666;
            font-size: 14px;
        }

        .value {
            font-size: 24px;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <h1>Gráficos en tiempo real</h1>
    <p>
        <a href="/servidor">← Volver al monitor</a>
        · Última lectura: <span id="at">esperando datos...</span>
    </p>
    <div class="grid">
        <x-grafico-metrica titulo="Uso de CPU (%)" campo="cpu" color="#1d5fa8" />
        <x-grafico-metrica titulo="Memoria usada (%)" campo="memory" color="#0f766e" />
        <x-grafico-metrica titulo="Disco usado (%)" campo="disk" color="#c2410c" tipo="medidor" />
    </div>
    <!-- En el Paso 6.4 va el script acá -->
    <script type="module">
        const MAX_PUNTOS = 30; // 30 lecturas x 5 segundos = 2 minutos y medio
        const graficos = {};
        // 1. Crear un gráfico por cada componente de la página
        document.querySelectorAll('canvas[data-grafico]').forEach((canvas) => {
            const {
                grafico: campo,
                tipo,
                color
            } = canvas.dataset;
            if (tipo === 'medidor') {
                graficos[campo] = new Chart(canvas, {
                    type: 'doughnut',
                    data: {
                        labels: ['Usado', 'Libre'],
                        datasets: [{
                            data: [0, 100],
                            backgroundColor: [color, '#e5e7eb'],
                            borderWidth: 0,
                        }],
                    },
                    options: {
                        aspectRatio: 2,
                        rotation: -90,
                        circumference: 180,
                        cutout: '70%',
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                    },
                });
            } else {
                graficos[campo] = new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: [{
                            data: [],
                            borderColor: color,
                            backgroundColor: color + '22',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 0,
                        }],
                    },
                    options: {
                        aspectRatio: 2,
                        animation: false,
                        scales: {
                            x: {
                                display: false
                            },
                            y: {
                                min: 0,
                                max: 100
                            },
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                    },
                });
            }
        });
        // 2. Formatear el valor como porcentaje (ej: 48.5 %)
        const formatearPct = (valor) => {
            const n = Number(valor);
            if (valor === null || valor === undefined || Number.isNaN(n)) {
                return 'No disponible';
            }
            return `${Number.isInteger(n) ? n : n.toFixed(1)} %`;
        };
        // 3. Agregar una lectura a un gráfico
        function agregar(campo, hora, valor) {
            document.getElementById(`valor-${campo}`).textContent = formatearPct(valor);
            const grafico = graficos[campo];
            if (!grafico || valor == null) return;
            if (grafico.config.type === 'doughnut') {
                grafico.data.datasets[0].data = [valor, 100 - valor];
            } else {
                grafico.data.labels.push(hora);
                grafico.data.datasets[0].data.push(valor);
                // Si hay más de 30 puntos, se borra el más viejo: el gráfico "corre"
                if (grafico.data.labels.length > MAX_PUNTOS) {
                    grafico.data.labels.shift();
                    grafico.data.datasets[0].data.shift();
                }
            }
            grafico.update();
        }
        // 4. Pintar una lectura completa de métricas
        function pintarMetricas(d) {
            const hora = new Date(d.at).toLocaleTimeString();
            document.getElementById('at').textContent = hora;
            agregar('cpu', hora, d.cpu_pct);
            agregar('memory', hora, d.memory?.used_pct);
            agregar('disk', hora, d.disk.used_pct);
        }
        // 5. Escuchar el MISMO canal que el monitor
        let websocketConectado = false;
        if (window.Echo) {
            window.Echo.connector.pusher.connection.bind('state_change', ({
                current
            }) => {
                websocketConectado = current === 'connected';
            });
            window.Echo.channel('server.metrics')
                .listen('MetricsUpdated', (e) => pintarMetricas(e.data));
        }
        // 6. Respaldo: si el WebSocket no está conectado, consultar la API
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
    </script>
</body>

</html
