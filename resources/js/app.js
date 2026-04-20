import Chart from 'chart.js/auto';

const product360Charts = new Map();

function destroyChartsForRoot(root) {
    const list = product360Charts.get(root);
    if (list) {
        list.forEach((c) => c.destroy());
        product360Charts.delete(root);
    }
}

function setupProduct360Charts() {
    document.querySelectorAll('[data-product-360-charts]').forEach((root) => {
        destroyChartsForRoot(root);

        const jsonEl = root.querySelector('[data-product-chart-json]');
        if (!jsonEl) {
            return;
        }

        let payload;
        try {
            payload = JSON.parse(jsonEl.textContent ?? '{}');
        } catch {
            return;
        }

        const instances = [];

        const formatDateLabel = (value) => {
            const parsed = new Date(value);
            if (Number.isNaN(parsed.getTime())) {
                return value;
            }

            return new Intl.DateTimeFormat('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
            }).format(parsed);
        };

        const makeLine = (canvasSelector, label, points, borderColor) => {
            const canvas = root.querySelector(canvasSelector);
            if (!canvas || !points?.length) {
                return;
            }

            instances.push(
                new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: points.map((p) => formatDateLabel(p.t)),
                        datasets: [
                            {
                                label,
                                data: points.map((p) => p.y),
                                borderColor,
                                backgroundColor: 'transparent',
                                tension: 0.15,
                                pointRadius: 2,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                ticks: {
                                    maxRotation: 60,
                                    minRotation: 60,
                                },
                            },
                        },
                    },
                }),
            );
        };

        makeLine(
            '[data-chart="inventory"]',
            'On hand',
            payload.inventoryBalance,
            'rgb(16, 185, 129)',
        );
        makeLine(
            '[data-chart="purchase"]',
            'Unit cost',
            payload.purchaseUnitCost,
            'rgb(59, 130, 246)',
        );
        makeLine(
            '[data-chart="sale"]',
            'Unit price',
            payload.saleUnitPrice,
            'rgb(168, 85, 247)',
        );

        product360Charts.set(root, instances);
    });
}

function teardownProduct360Charts() {
    document.querySelectorAll('[data-product-360-charts]').forEach((root) => {
        destroyChartsForRoot(root);
    });
}

document.addEventListener('livewire:navigating', teardownProduct360Charts);
document.addEventListener('livewire:navigated', setupProduct360Charts);
document.addEventListener('DOMContentLoaded', setupProduct360Charts);

document.addEventListener('livewire:init', () => {
    Livewire.hook('morph.updated', () => {
        queueMicrotask(setupProduct360Charts);
    });
});
