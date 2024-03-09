<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Item Response Graph</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <canvas id="irfChart" width="800" height="400"></canvas>
    <script>

    const data = @json($data);
    
console.log(data);
        // Mengolah data untuk Chart.js
        const labels = data.original.map(d => `Item ${d.item}, Siswa ${d.student}`);
        const probabilities = data.original.map(d => d.probability);

        const ctx = document.getElementById('irfChart').getContext('2d');
        const irfChart = new Chart(ctx, {
            type: 'line', // atau 'bar' tergantung pada preferensi Anda
            data: {
                labels: labels,
                datasets: [{
                    label: 'Probabilitas Menjawab Benar',
                    data: probabilities,
                    fill: false,
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
