const canvas = document.getElementById('landing-canvas');
const ctx = canvas.getContext('2d');

let width = 0;
let height = 0;
let nodes = [];
let pointer = { x: 0, y: 0, active: false };

const colors = ['#60a5fa', '#a78bfa', '#34d399', '#fbbf24'];

function resizeCanvas() {
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    width = window.innerWidth;
    height = window.innerHeight;
    canvas.width = Math.floor(width * dpr);
    canvas.height = Math.floor(height * dpr);
    canvas.style.width = `${width}px`;
    canvas.style.height = `${height}px`;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    const count = Math.max(26, Math.min(56, Math.floor(width / 28)));
    nodes = Array.from({ length: count }, (_, index) => ({
        x: Math.random() * width,
        y: Math.random() * height,
        vx: (Math.random() - 0.5) * 0.45,
        vy: (Math.random() - 0.5) * 0.45,
        w: 54 + Math.random() * 42,
        h: 20 + Math.random() * 8,
        color: colors[index % colors.length],
        phase: Math.random() * Math.PI * 2
    }));
}

function drawNode(node, time) {
    const drift = Math.sin(time * 0.001 + node.phase) * 4;
    const x = node.x;
    const y = node.y + drift;

    ctx.save();
    ctx.translate(x, y);
    ctx.fillStyle = 'rgba(255, 255, 255, 0.08)';
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.18)';
    ctx.lineWidth = 1;
    roundedRect(-node.w / 2, -node.h / 2, node.w, node.h, 8);
    ctx.fill();
    ctx.stroke();

    ctx.fillStyle = node.color;
    ctx.beginPath();
    ctx.arc(-node.w / 2 + 12, 0, 3, 0, Math.PI * 2);
    ctx.fill();

    ctx.fillStyle = 'rgba(255, 255, 255, 0.72)';
    ctx.font = '10px "DM Mono", monospace';
    ctx.fillText(`#${Math.floor(1000 + node.phase * 100)}`, -node.w / 2 + 22, 3);
    ctx.restore();
}

function roundedRect(x, y, w, h, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.lineTo(x + w - r, y);
    ctx.quadraticCurveTo(x + w, y, x + w, y + r);
    ctx.lineTo(x + w, y + h - r);
    ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
    ctx.lineTo(x + r, y + h);
    ctx.quadraticCurveTo(x, y + h, x, y + h - r);
    ctx.lineTo(x, y + r);
    ctx.quadraticCurveTo(x, y, x + r, y);
    ctx.closePath();
}

function animate(time) {
    ctx.clearRect(0, 0, width, height);
    ctx.fillStyle = '#111827';
    ctx.fillRect(0, 0, width, height);

    for (let i = 0; i < nodes.length; i += 1) {
        const a = nodes[i];
        a.x += a.vx;
        a.y += a.vy;

        if (a.x < -80) a.x = width + 80;
        if (a.x > width + 80) a.x = -80;
        if (a.y < -60) a.y = height + 60;
        if (a.y > height + 60) a.y = -60;

        for (let j = i + 1; j < nodes.length; j += 1) {
            const b = nodes[j];
            const dx = a.x - b.x;
            const dy = a.y - b.y;
            const distance = Math.hypot(dx, dy);
            if (distance < 190) {
                ctx.strokeStyle = `rgba(148, 163, 184, ${0.18 * (1 - distance / 190)})`;
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(a.x, a.y);
                ctx.lineTo(b.x, b.y);
                ctx.stroke();
            }
        }

        if (pointer.active) {
            const dx = a.x - pointer.x;
            const dy = a.y - pointer.y;
            const distance = Math.hypot(dx, dy);
            if (distance < 220) {
                ctx.strokeStyle = `rgba(96, 165, 250, ${0.28 * (1 - distance / 220)})`;
                ctx.beginPath();
                ctx.moveTo(a.x, a.y);
                ctx.lineTo(pointer.x, pointer.y);
                ctx.stroke();
            }
        }
    }

    nodes.forEach((node) => drawNode(node, time));
    requestAnimationFrame(animate);
}

window.addEventListener('resize', resizeCanvas);
window.addEventListener('pointermove', (event) => {
    pointer = { x: event.clientX, y: event.clientY, active: true };
});
window.addEventListener('pointerleave', () => {
    pointer.active = false;
});

resizeCanvas();
requestAnimationFrame(animate);
