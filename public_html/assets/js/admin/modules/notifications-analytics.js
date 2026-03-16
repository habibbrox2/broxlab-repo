const byIdDefault = (id) => document.getElementById(id);

export function initNotificationsAnalytics(options = {}) {
    const byId = options.byId || byIdDefault;

    const flowChart = byId('notificationFlowChart');
    const channelChart = byId('channelChart');
    if (!flowChart || !channelChart || typeof Chart === 'undefined') return;

    const flowCtx = flowChart.getContext('2d');
    new Chart(flowCtx, {
        type: 'bar',
        data: {
            labels: ['Sent', 'Delivered', 'Opened', 'Clicked'],
            datasets: [{
                label: 'Notification Flow',
                data: [1000, 850, 620, 380],
                backgroundColor: [
                    'rgba(13, 110, 253, 0.8)',
                    'rgba(25, 135, 84, 0.8)',
                    'rgba(0, 123, 255, 0.8)',
                    'rgba(255, 193, 7, 0.8)'
                ],
                borderColor: [
                    'rgba(13, 110, 253, 1)',
                    'rgba(25, 135, 84, 1)',
                    'rgba(0, 123, 255, 1)',
                    'rgba(255, 193, 7, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            indexAxis: 'y',
            plugins: { legend: { display: true } },
            scales: {
                x: { beginAtZero: true, ticks: { color: '#666' } },
                y: { ticks: { color: '#666' } }
            }
        }
    });

    const channelCtx = channelChart.getContext('2d');
    new Chart(channelCtx, {
        type: 'doughnut',
        data: {
            labels: ['Push', 'Email', 'In-App'],
            datasets: [{
                data: [65, 20, 15],
                backgroundColor: [
                    'rgba(13, 110, 253, 0.8)',
                    'rgba(25, 135, 84, 0.8)',
                    'rgba(0, 123, 255, 0.8)'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    byId('totalNotificationsSent').textContent = '1,234';
    byId('totalDelivered').textContent = '1,050 (85%)';
    byId('totalClicked').textContent = '380 (36%)';
    byId('totalPermissionGranted').textContent = '2,456 (92%)';
}
