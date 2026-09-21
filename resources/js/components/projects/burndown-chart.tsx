import { useMemo, useState } from 'react';
import type { BurndownPoint } from '@/types';

type Props = {
    points: BurndownPoint[];
};

const WIDTH = 640;
const HEIGHT = 220;
const PADDING = { top: 16, right: 16, bottom: 28, left: 32 };

export default function BurndownChart({ points }: Props) {
    const [hoverIndex, setHoverIndex] = useState<number | null>(null);

    const chart = useMemo(() => {
        if (points.length < 2) {
            return null;
        }

        const innerWidth = WIDTH - PADDING.left - PADDING.right;
        const innerHeight = HEIGHT - PADDING.top - PADDING.bottom;
        const maxOpen = Math.max(1, ...points.map((p) => p.open));

        const x = (index: number) =>
            PADDING.left + (index / (points.length - 1)) * innerWidth;
        const y = (open: number) =>
            PADDING.top + innerHeight - (open / maxOpen) * innerHeight;

        const linePath = points
            .map((point, index) => `${index === 0 ? 'M' : 'L'} ${x(index)} ${y(point.open)}`)
            .join(' ');

        const areaPath =
            `${linePath} ` +
            `L ${x(points.length - 1)} ${PADDING.top + innerHeight} ` +
            `L ${x(0)} ${PADDING.top + innerHeight} Z`;

        const yTicks = [0, 0.5, 1].map((fraction) => ({
            value: Math.round(maxOpen * fraction),
            y: y(maxOpen * fraction),
        }));

        return { x, y, linePath, areaPath, maxOpen, yTicks, innerWidth };
    }, [points]);

    if (!chart || points.length === 0) {
        return (
            <p
                className="text-muted-foreground py-8 text-center text-sm"
                data-test="burndown-empty"
            >
                Not enough history yet. The nightly snapshot job needs at
                least two days of data to draw a burndown.
            </p>
        );
    }

    const hovered = hoverIndex !== null ? points[hoverIndex] : null;
    const last = points[points.length - 1];

    const handlePointerMove = (event: React.PointerEvent<SVGRectElement>) => {
        const bounds = event.currentTarget.getBoundingClientRect();
        const relativeX = event.clientX - bounds.left;
        const ratio = relativeX / bounds.width;
        const index = Math.round(ratio * (points.length - 1));
        setHoverIndex(Math.min(points.length - 1, Math.max(0, index)));
    };

    return (
        <div className="space-y-1" data-test="burndown-chart">
            <svg
                viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
                className="w-full"
                role="img"
                aria-label="Open tasks over the last 30 days"
            >
                {chart.yTicks.map((tick) => (
                    <g key={tick.value}>
                        <line
                            x1={PADDING.left}
                            x2={WIDTH - PADDING.right}
                            y1={tick.y}
                            y2={tick.y}
                            className="stroke-border"
                            strokeWidth={1}
                        />
                        <text
                            x={PADDING.left - 8}
                            y={tick.y}
                            textAnchor="end"
                            dominantBaseline="middle"
                            className="fill-muted-foreground text-[10px]"
                        >
                            {tick.value}
                        </text>
                    </g>
                ))}

                <path d={chart.areaPath} className="fill-chart-1" fillOpacity={0.1} />
                <path
                    d={chart.linePath}
                    className="stroke-chart-1"
                    strokeWidth={2}
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    fill="none"
                />

                <circle
                    cx={chart.x(points.length - 1)}
                    cy={chart.y(last.open)}
                    r={4}
                    className="fill-chart-1 stroke-background"
                    strokeWidth={2}
                />
                <text
                    x={chart.x(points.length - 1)}
                    y={chart.y(last.open) - 10}
                    textAnchor="end"
                    className="fill-foreground text-[11px] font-medium"
                >
                    {last.open} open
                </text>

                {hovered ? (
                    <>
                        <line
                            x1={chart.x(hoverIndex ?? 0)}
                            x2={chart.x(hoverIndex ?? 0)}
                            y1={PADDING.top}
                            y2={HEIGHT - PADDING.bottom}
                            className="stroke-muted-foreground"
                            strokeWidth={1}
                            strokeDasharray="2,2"
                        />
                        <circle
                            cx={chart.x(hoverIndex ?? 0)}
                            cy={chart.y(hovered.open)}
                            r={4}
                            className="fill-chart-1 stroke-background"
                            strokeWidth={2}
                        />
                    </>
                ) : null}

                <text
                    x={PADDING.left}
                    y={HEIGHT - 6}
                    className="fill-muted-foreground text-[10px]"
                >
                    {points[0].date}
                </text>
                <text
                    x={WIDTH - PADDING.right}
                    y={HEIGHT - 6}
                    textAnchor="end"
                    className="fill-muted-foreground text-[10px]"
                >
                    {last.date}
                </text>

                <rect
                    x={PADDING.left}
                    y={PADDING.top}
                    width={chart.innerWidth}
                    height={HEIGHT - PADDING.top - PADDING.bottom}
                    fill="transparent"
                    onPointerMove={handlePointerMove}
                    onPointerLeave={() => setHoverIndex(null)}
                    data-test="burndown-hover-area"
                />
            </svg>

            {hovered ? (
                <div
                    className="text-muted-foreground flex justify-between text-xs"
                    data-test="burndown-tooltip"
                >
                    <span>{hovered.date}</span>
                    <span>
                        <strong className="text-foreground">{hovered.open}</strong>{' '}
                        open · {hovered.completed}/{hovered.total} done
                    </span>
                </div>
            ) : null}
        </div>
    );
}
