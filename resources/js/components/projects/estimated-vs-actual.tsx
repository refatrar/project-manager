import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { EstimatedVsActualRow } from '@/types';

type Props = {
    rows: EstimatedVsActualRow[];
};

export default function EstimatedVsActual({ rows }: Props) {
    if (rows.length === 0) {
        return null;
    }

    return (
        <Card data-test="estimated-vs-actual">
            <CardHeader>
                <CardTitle>Estimated vs actual</CardTitle>
            </CardHeader>
            <CardContent className="overflow-x-auto">
                <table className="w-full min-w-100 border-collapse text-sm">
                    <thead>
                        <tr className="border-b text-left">
                            <th className="py-2 pr-4 font-medium">Member</th>
                            <th className="py-2 pr-4 text-right font-medium">
                                Booked
                            </th>
                            <th className="py-2 pr-4 text-right font-medium">
                                Logged
                            </th>
                            <th className="py-2 text-right font-medium">
                                Variance
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr
                                key={row.user.id}
                                data-test="estimated-vs-actual-row"
                                className="border-b last:border-0"
                            >
                                <td className="py-2 pr-4">{row.user.name}</td>
                                <td className="py-2 pr-4 text-right tabular-nums">
                                    {row.estimated_hours}h
                                </td>
                                <td className="py-2 pr-4 text-right tabular-nums">
                                    {row.actual_hours}h
                                </td>
                                <td
                                    className={`py-2 text-right text-sm font-medium tabular-nums ${
                                        row.variance_hours > 0
                                            ? 'text-destructive'
                                            : 'text-muted-foreground'
                                    }`}
                                >
                                    {row.variance_hours > 0 ? '+' : ''}
                                    {row.variance_hours}h
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <p className="text-muted-foreground mt-3 text-xs">
                    Booked is each member&apos;s planned hours across every
                    booking on this project (hours/day × days). Logged excludes
                    rejected and cancelled time entries. A positive variance
                    means more was logged than booked.
                </p>
            </CardContent>
        </Card>
    );
}
