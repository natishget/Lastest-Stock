import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { AlertTriangle, LoaderCircle } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

type CostingMethod = 'FIFO' | 'LIFO' | 'WEIGHTED_AVERAGE';

interface CogsRow {
    variant_id: string;
    product_name: string;
    color: string | null;
    origin: string | null;
    quantity_sold: string;
    revenue: string;
    cogs: string;
    gross_profit: string;
    profit_margin: string;
}

interface CogsResponse {
    data: CogsRow[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
}

interface CogsPageProps {
    activeMethod: CostingMethod;
    methodOptions: CostingMethod[];
    defaultFilters: {
        startDate: string;
        endDate: string;
        costingMethod: CostingMethod;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'COGS',
        href: '/cogs',
    },
];

const requestCache = new Map<string, CogsResponse>();

function money(value: string | number): string {
    const numericValue = typeof value === 'string' ? Number(value) : value;

    return Number.isFinite(numericValue)
        ? numericValue.toLocaleString(undefined, {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          })
        : '-';
}

function percent(value: string | number): string {
    const numericValue = typeof value === 'string' ? Number(value) : value;

    return Number.isFinite(numericValue) ? `${numericValue.toFixed(2)}%` : '-';
}

export default function CogsIndex({ activeMethod, methodOptions, defaultFilters }: CogsPageProps) {
    const [startDate, setStartDate] = useState(defaultFilters.startDate);
    const [endDate, setEndDate] = useState(defaultFilters.endDate);
    const [costingMethod, setCostingMethod] = useState<CostingMethod>(defaultFilters.costingMethod);
    const [page, setPage] = useState(1);
    const [isLoading, setIsLoading] = useState(true);
    const [isCalculating, setIsCalculating] = useState(false);
    const [rows, setRows] = useState<CogsResponse | null>(null);
    const [loadError, setLoadError] = useState<string | null>(null);
    const debounceTimer = useRef<number | null>(null);

    const queryKey = useMemo(() => JSON.stringify({ startDate, endDate, costingMethod, page }), [costingMethod, endDate, page, startDate]);

    useEffect(() => {
        requestCache.clear();
    }, []);

    useEffect(() => {
        if (debounceTimer.current) {
            window.clearTimeout(debounceTimer.current);
        }

        setIsCalculating(true);

        debounceTimer.current = window.setTimeout(async () => {
            if (requestCache.has(queryKey)) {
                setRows(requestCache.get(queryKey) ?? null);
                setIsLoading(false);
                setIsCalculating(false);
                setLoadError(null);
                return;
            }

            try {
                const response = await fetch(
                    `/cogs/report?start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}&costing_method=${encodeURIComponent(costingMethod)}&page=${page}&per_page=10`,
                    {
                        headers: {
                            Accept: 'application/json',
                        },
                        credentials: 'same-origin',
                    },
                );

                if (!response.ok) {
                    throw new Error('Failed to load COGS data.');
                }

                const payload = (await response.json()) as CogsResponse;
                requestCache.set(queryKey, payload);
                setRows(payload);
                setLoadError(null);
            } catch {
                setRows(null);
                setLoadError('Unable to load COGS report data. Please refresh and try again.');
            } finally {
                setIsLoading(false);
                setIsCalculating(false);
            }
        }, 300);

        return () => {
            if (debounceTimer.current) {
                window.clearTimeout(debounceTimer.current);
            }
        };
    }, [costingMethod, endDate, page, queryKey, startDate]);

    useEffect(() => {
        setPage(1);
    }, [costingMethod, endDate, startDate]);

    const metrics = useMemo(() => {
        const totalRevenue = rows?.data.reduce((sum, row) => sum + Number(row.revenue), 0) ?? 0;
        const totalCogs = rows?.data.reduce((sum, row) => sum + Number(row.cogs), 0) ?? 0;
        const totalProfit = totalRevenue - totalCogs;

        return {
            revenue: totalRevenue,
            cogs: totalCogs,
            profit: totalProfit,
        };
    }, [rows]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="COGS" />

            <div className="space-y-6 p-4">
                <div className="rounded-xl border p-4 md:p-6">
                    <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div>
                            <h1 className="text-2xl font-semibold">COGS Report</h1>
                            <p className="text-muted-foreground mt-1 text-sm">Track revenue, COGS, gross profit, and margin by product variant.</p>
                        </div>

                        <Badge variant="outline" className="w-fit rounded-md px-3 py-1 text-xs">
                            Active method: {activeMethod}
                        </Badge>
                    </div>

                    {costingMethod !== activeMethod ? (
                        <Alert className="mt-4 border-amber-300 bg-amber-50 text-amber-900">
                            <AlertTriangle className="h-4 w-4" />
                            <AlertTitle>Viewing an alternate costing method</AlertTitle>
                            <AlertDescription>
                                Results are loaded from stored COGS entries for the selected method. Use this to compare costing scenarios without
                                changing stored data.
                            </AlertDescription>
                        </Alert>
                    ) : null}

                    {loadError ? (
                        <Alert className="mt-4 border-red-300 bg-red-50 text-red-900">
                            <AlertTriangle className="h-4 w-4" />
                            <AlertTitle>Report Loading Failed</AlertTitle>
                            <AlertDescription>{loadError}</AlertDescription>
                        </Alert>
                    ) : null}

                    <div className="mt-6 grid gap-4 md:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor="start-date">Start Date</Label>
                            <Input id="start-date" type="date" value={startDate} onChange={(event) => setStartDate(event.target.value)} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="end-date">End Date</Label>
                            <Input id="end-date" type="date" value={endDate} onChange={(event) => setEndDate(event.target.value)} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="method">Costing Method</Label>
                            <Select value={costingMethod} onValueChange={(value) => setCostingMethod(value as CostingMethod)}>
                                <SelectTrigger id="method">
                                    <SelectValue placeholder="Select method" />
                                </SelectTrigger>
                                <SelectContent>
                                    {methodOptions.map((method) => (
                                        <SelectItem key={method} value={method}>
                                            {method.replace('_', ' ')}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-medium">Revenue</CardTitle>
                        </CardHeader>
                        <CardContent className="text-2xl font-semibold">{money(metrics.revenue)}</CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-medium">COGS</CardTitle>
                        </CardHeader>
                        <CardContent className="text-2xl font-semibold">{money(metrics.cogs)}</CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-medium">Gross Profit</CardTitle>
                        </CardHeader>
                        <CardContent className="text-2xl font-semibold">{money(metrics.profit)}</CardContent>
                    </Card>
                </div>

                <div className="overflow-hidden rounded-xl border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/40">
                                <tr>
                                    <th className="px-4 py-3 text-left font-medium">Product Name</th>
                                    <th className="px-4 py-3 text-left font-medium">Color</th>
                                    <th className="px-4 py-3 text-left font-medium">Origin</th>
                                    <th className="px-4 py-3 text-right font-medium">Quantity Sold</th>
                                    <th className="px-4 py-3 text-right font-medium">Revenue</th>
                                    <th className="px-4 py-3 text-right font-medium">COGS</th>
                                    <th className="px-4 py-3 text-right font-medium">Gross Profit</th>
                                    <th className="px-4 py-3 text-right font-medium">Profit Margin %</th>
                                </tr>
                            </thead>
                            <tbody>
                                {isLoading || isCalculating ? (
                                    <tr>
                                        <td colSpan={8} className="px-4 py-8 text-center">
                                            <div className="inline-flex items-center gap-2 text-sm">
                                                <LoaderCircle className="h-4 w-4 animate-spin" />
                                                Calculating...
                                            </div>
                                        </td>
                                    </tr>
                                ) : rows?.data.length ? (
                                    rows.data.map((row) => (
                                        <tr key={row.variant_id} className="border-t">
                                            <td className="px-4 py-3 font-medium">{row.product_name}</td>
                                            <td className="px-4 py-3">{row.color ?? '-'}</td>
                                            <td className="px-4 py-3">{row.origin ?? '-'}</td>
                                            <td className="px-4 py-3 text-right">{Number(row.quantity_sold).toLocaleString()}</td>
                                            <td className="px-4 py-3 text-right">{money(row.revenue)}</td>
                                            <td className="px-4 py-3 text-right">{money(row.cogs)}</td>
                                            <td className="px-4 py-3 text-right">{money(row.gross_profit)}</td>
                                            <td className="px-4 py-3 text-right">{percent(row.profit_margin)}</td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={8} className="text-muted-foreground px-4 py-8 text-center">
                                            No COGS records found for the selected filters.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="flex items-center justify-between gap-3">
                    <p className="text-muted-foreground text-sm">
                        Showing {rows?.from ?? 0}-{rows?.to ?? 0} of {rows?.total ?? 0}
                    </p>

                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                requestCache.clear();
                                setIsLoading(true);
                                setIsCalculating(true);
                                setRows(null);
                                setLoadError(null);
                                setPage(1);
                            }}
                        >
                            Refresh
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setPage((currentPage) => Math.max(1, currentPage - 1))}
                            disabled={page <= 1}
                        >
                            Previous
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setPage((currentPage) => currentPage + 1)}
                            disabled={rows ? page >= rows.last_page : true}
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
