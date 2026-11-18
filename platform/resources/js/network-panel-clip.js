// Pixel-space tangent arcs keep diagonal and square corners equally rounded.
export function roundedNetworkPanelPath(width, height, cut, radius, shape = 'middle') {
    if (![width, height, cut, radius].every(Number.isFinite) || width <= 0 || height <= 0) return '';
    const inset = Math.min(Math.max(0, cut), width * .45);
    const points = shape === 'single' ? [[0, 0], [width, 0], [width, height], [0, height]]
        : [[shape === 'first' ? 0 : inset, 0], [width, 0], [shape === 'last' ? width : width - inset, height], [0, height]];
    const corners = points.map((point, index) => {
        const previous = points[(index + 3) % 4];
        const next = points[(index + 1) % 4];
        const beforeLength = Math.hypot(previous[0] - point[0], previous[1] - point[1]);
        const afterLength = Math.hypot(next[0] - point[0], next[1] - point[1]);
        const before = [(previous[0] - point[0]) / beforeLength, (previous[1] - point[1]) / beforeLength];
        const after = [(next[0] - point[0]) / afterLength, (next[1] - point[1]) / afterLength];
        const tangent = Math.tan(Math.acos(Math.max(-1, Math.min(1, before[0] * after[0] + before[1] * after[1]))) / 2);
        const distance = Math.min(Math.max(0, radius) / tangent, beforeLength * .49, afterLength * .49);
        return {
            entry: [point[0] + before[0] * distance, point[1] + before[1] * distance],
            exit: [point[0] + after[0] * distance, point[1] + after[1] * distance],
            radius: distance * tangent,
        };
    });
    const number = value => Number(value.toFixed(7));
    const pair = point => `${number(point[0] / width)} ${number(point[1] / height)}`;
    return `M ${pair(corners[0].entry)} ${corners.map((corner, index) =>
        `A ${number(corner.radius / width)} ${number(corner.radius / height)} 0 0 1 ${pair(corner.exit)} L ${pair(corners[(index + 1) % 4].entry)}`
    ).join(' ')} Z`;
}
