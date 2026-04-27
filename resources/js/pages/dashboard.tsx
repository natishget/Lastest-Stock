import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { AlertTriangle, BarChart3, LineChart, LoaderCircle, RefreshCcw } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

type FiscalYear = number;

interface FinancialMonth {
    month: string;
    label: string;
    revenue: number;
    cogs: number;
    gross_profit: number;
}

interface FinancialResponse {
    fiscal_year: number;
    start_date: string;
    end_date: string;
    months: FinancialMonth[];
    totals: {
        revenue: number;
        cogs: number;
        gross_profit: number;
    };
}

interface TopProductRow {
    variant_id: string;
    product_name: string;
    color: string | null;
    origin: string | null;
    label: string;
    total_quantity: number;
    revenue: number;
}

interface TopProductsResponse {
    fiscal_year: number;
    start_date: string;
    end_date: string;
    limit: number;
    data: TopProductRow[];
}

interface DashboardPageProps {
    defaultFiscalYear: FiscalYear;
    fiscalYears: FiscalYear[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const requestCache = new Map<string, unknown>();

function formatMoney(value: number): string {
    return value.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function formatMonthLabel(month: string): string {
    const [year, monthNumber] = month.split('-').map(Number);
    const date = new Date(year, monthNumber - 1, 1);

    return date.toLocaleDateString(undefined, {
        month: 'short',
        year: 'numeric',
    });
}

function createLinePath(points: Array<{ x: number; y: number }>): string {
    if (points.length === 0) {
        return '';
    }

    return points.reduce((path, point, index) => `${path}${index === 0 ? 'M' : 'L'}${point.x},${point.y}`, '');
}

function useDashboardData(fiscalYear: FiscalYear) {
    const [financials, setFinancials] = useState<FinancialResponse | null>(null);
    const [topProducts, setTopProducts] = useState<TopProductsResponse | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [refreshTick, setRefreshTick] = useState(0);

    useEffect(() => {
        let isActive = true;
        const financialsKey = `dashboard.financials.${fiscalYear}`;
        const topProductsKey = `dashboard.top.${fiscalYear}`;

        const load = async () => {
            setIsLoading(true);
            setError(null);

            try {
                const cachedFinancials = requestCache.get(financialsKey) as FinancialResponse | undefined;
                const cachedTopProducts = requestCache.get(topProductsKey) as TopProductsResponse | undefined;

                if (cachedFinancials && cachedTopProducts) {
                    if (isActive) {
                        setFinancials(cachedFinancials);
                        setTopProducts(cachedTopProducts);
                        setIsLoading(false);
                    }
                    return;
                }

                const [financialsResponse, topProductsResponse] = await Promise.all([
                    fetch(`/dashboard/financials?fiscal_year=${encodeURIComponent(String(fiscalYear))}`, {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    }),
                    fetch(`/dashboard/top-products?fiscal_year=${encodeURIComponent(String(fiscalYear))}&limit=12`, {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    }),
                ]);

                if (!financialsResponse.ok || !topProductsResponse.ok) {
                    throw new Error(`Unable to load dashboard data (${financialsResponse.status}/${topProductsResponse.status}).`);
                }

                const financialsPayload = (await financialsResponse.json()) as FinancialResponse;
                const topProductsPayload = (await topProductsResponse.json()) as TopProductsResponse;

                requestCache.set(financialsKey, financialsPayload);
                requestCache.set(topProductsKey, topProductsPayload);

                if (isActive) {
                    setFinancials(financialsPayload);
                    setTopProducts(topProductsPayload);
                }
            } catch (loadError) {
                if (isActive) {
                    setError(loadError instanceof Error ? loadError.message : 'Unable to load dashboard data.');
                }
            } finally {
                if (isActive) {
                    setIsLoading(false);
                }
            }
        };

        load();

        return () => {
            isActive = false;
        };
    }, [fiscalYear, refreshTick]);

    return {
        financials,
        topProducts,
        isLoading,
        error,
        refresh: () => {
            requestCache.clear();
            setRefreshTick((current) => current + 1);
        },
    };
}

function FinancialLineChart({ data }: { data: FinancialMonth[] }) {
    const width = 960;
    const height = 320;
    const padding = { top: 28, right: 24, bottom: 44, left: 64 };
    const innerWidth = width - padding.left - padding.right;
    const innerHeight = height - padding.top - padding.bottom;

    const series = useMemo(() => {
        const allValues = data.flatMap((item) => [item.revenue, item.cogs, item.gross_profit]);
        const maxValue = Math.max(...allValues, 0);
        const yMax = maxValue <= 0 ? 1 : maxValue * 1.15;

        const scaleX = (index: number) => padding.left + (index * innerWidth) / Math.max(data.length - 1, 1);
        const scaleY = (value: number) => padding.top + innerHeight - (value / yMax) * innerHeight;

        const revenuePoints = data.map((item, index) => ({ x: scaleX(index), y: scaleY(item.revenue) }));
        const cogsPoints = data.map((item, index) => ({ x: scaleX(index), y: scaleY(item.cogs) }));
        const profitPoints = data.map((item, index) => ({ x: scaleX(index), y: scaleY(item.gross_profit) }));

        return {
            yMax,
            revenuePath: createLinePath(revenuePoints),
            cogsPath: createLinePath(cogsPoints),
            profitPath: createLinePath(profitPoints),
            scaleX,
            scaleY,
            revenuePoints,
            cogsPoints,
            profitPoints,
        };
    }, [data, innerHeight, innerWidth, padding.left, padding.top]);

    const yTicks = useMemo(() => {
        const tickCount = 4;
        return Array.from({ length: tickCount + 1 }, (_, index) => (series.yMax / tickCount) * (tickCount - index));
    }, [series.yMax]);

    return (
        <div className="bg-background/80 overflow-hidden rounded-2xl border p-4 shadow-sm">
            <div className="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h3 className="text-lg font-semibold">12-Month Financial Trend</h3>
                    <p className="text-muted-foreground text-sm">Revenue, COGS, and gross profit for the selected Ethiopian fiscal year.</p>
                </div>
                <Badge variant="outline" className="h-fit rounded-md px-3 py-1">
                    <LineChart className="mr-2 h-3.5 w-3.5" />
                    Monthly view
                </Badge>
            </div>

            <div className="overflow-x-auto">
                <svg viewBox={`0 0 ${width} ${height}`} className="min-w-full">
                    {yTicks.map((tick) => {
                        const y = padding.top + innerHeight - (tick / series.yMax) * innerHeight;

                        return (
                            <g key={tick}>
                                <line
                                    x1={padding.left}
                                    x2={width - padding.right}
                                    y1={y}
                                    y2={y}
                                    stroke="currentColor"
                                    className="text-border/70"
                                    strokeDasharray="4 4"
                                />
                                <text x={padding.left - 12} y={y + 4} textAnchor="end" className="fill-muted-foreground text-[11px]">
                                    {formatMoney(tick)}
                                </text>
                            </g>
                        );
                    })}

                    {data.map((item, index) => {
                        const x = series.scaleX(index);
                        return (
                            <text key={item.month} x={x} y={height - 14} textAnchor="middle" className="fill-muted-foreground text-[11px]">
                                {item.label}
                            </text>
                        );
                    })}

                    <path d={series.revenuePath} fill="none" stroke="#0f766e" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />
                    <path d={series.cogsPath} fill="none" stroke="#c2410c" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />
                    <path d={series.profitPath} fill="none" stroke="#1d4ed8" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />

                    {series.revenuePoints.map((point, index) => (
                        <circle key={`revenue-${data[index].month}`} cx={point.x} cy={point.y} r="4" fill="#0f766e" />
                    ))}
                    {series.cogsPoints.map((point, index) => (
                        <circle key={`cogs-${data[index].month}`} cx={point.x} cy={point.y} r="4" fill="#c2410c" />
                    ))}
                    {series.profitPoints.map((point, index) => (
                        <circle key={`profit-${data[index].month}`} cx={point.x} cy={point.y} r="4" fill="#1d4ed8" />
                    ))}
                </svg>
            </div>

            <div className="text-muted-foreground mt-4 flex flex-wrap gap-3 text-xs">
                <span className="inline-flex items-center gap-2">
                    <span className="h-2.5 w-2.5 rounded-full bg-teal-700" />
                    Revenue
                </span>
                <span className="inline-flex items-center gap-2">
                    <span className="h-2.5 w-2.5 rounded-full bg-orange-700" />
                    COGS
                </span>
                <span className="inline-flex items-center gap-2">
                    <span className="h-2.5 w-2.5 rounded-full bg-blue-700" />
                    Gross Profit
                </span>
            </div>
        </div>
    );
}

function TopProductsChart({ data }: { data: TopProductRow[] }) {
    const width = 960;
    const rowHeight = 34;
    const height = Math.max(220, data.length * rowHeight + 48);
    const padding = { top: 20, right: 28, bottom: 28, left: 320 };
    const innerWidth = width - padding.left - padding.right;
    const maxQuantity = Math.max(...data.map((item) => item.total_quantity), 0);
    const maxScale = maxQuantity <= 0 ? 1 : maxQuantity;

    return (
        <div className="bg-background/80 overflow-hidden rounded-2xl border p-4 shadow-sm">
            <div className="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h3 className="text-lg font-semibold">Top Product Variants</h3>
                    <p className="text-muted-foreground text-sm">Highest selling variants by quantity within the selected fiscal year.</p>
                </div>
                <Badge variant="outline" className="h-fit rounded-md px-3 py-1">
                    <BarChart3 className="mr-2 h-3.5 w-3.5" />
                    Top sellers
                </Badge>
            </div>

            <div className="overflow-x-auto">
                <svg viewBox={`0 0 ${width} ${height}`} className="min-w-full">
                    {data.map((item, index) => {
                        const y = padding.top + index * rowHeight;
                        const barWidth = (item.total_quantity / maxScale) * innerWidth;

                        return (
                            <g key={item.variant_id}>
                                <text x={padding.left - 12} y={y + 18} textAnchor="end" className="fill-foreground text-[12px] font-medium">
                                    {item.label}
                                </text>
                                <rect x={padding.left} y={y + 4} width={innerWidth} height="18" rx="9" className="fill-muted/60" />
                                <rect x={padding.left} y={y + 4} width={barWidth} height="18" rx="9" fill="url(#bar-gradient)" />
                                <text x={padding.left + barWidth + 10} y={y + 17} className="fill-muted-foreground text-[11px]">
                                    {item.total_quantity.toFixed(0)} units · {formatMoney(item.revenue)}
                                </text>
                            </g>
                        );
                    })}

                    <defs>
                        <linearGradient id="bar-gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" stopColor="#0f766e" />
                            <stop offset="100%" stopColor="#2563eb" />
                        </linearGradient>
                    </defs>
                </svg>
            </div>
        </div>
    );
}

export default function Dashboard({ defaultFiscalYear, fiscalYears }: DashboardPageProps) {
    const [selectedFiscalYear, setSelectedFiscalYear] = useState(defaultFiscalYear);
    const { financials, topProducts, isLoading, error, refresh } = useDashboardData(selectedFiscalYear);

    const summaryCards = useMemo(
        () => [
            {
                title: 'Revenue',
                value: financials ? formatMoney(financials.totals.revenue) : '0.00',
                tone: 'emerald',
            },
            {
                title: 'COGS',
                value: financials ? formatMoney(financials.totals.cogs) : '0.00',
                tone: 'orange',
            },
            {
                title: 'Gross Profit',
                value: financials ? formatMoney(financials.totals.gross_profit) : '0.00',
                tone: 'blue',
            },
            {
                title: 'Top Products',
                value: topProducts ? String(topProducts.data.length) : '0',
                tone: 'slate',
            },
        ],
        [financials, topProducts],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="space-y-6 p-4">
                <div className="relative overflow-hidden rounded-3xl border bg-gradient-to-br from-slate-950 via-slate-900 to-teal-900 p-6 text-white shadow-lg">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(20,184,166,0.25),transparent_35%),radial-gradient(circle_at_bottom_left,rgba(59,130,246,0.18),transparent_30%)]" />
                    <div className="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div className="max-w-2xl space-y-3">
                            <p className="text-xs tracking-[0.35em] text-teal-200/80 uppercase">Financial dashboard</p>
                            <h1 className="text-3xl font-semibold tracking-tight md:text-4xl">Revenue, COGS, and gross profit at a glance.</h1>
                            <p className="max-w-xl text-sm text-slate-200/80">
                                Aggregated from stored sales, COGS entries, and posted transactions for the selected Ethiopian fiscal year.
                            </p>
                        </div>

                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div className="grid gap-2">
                                <label className="text-sm font-medium text-slate-100" htmlFor="fiscal-year">
                                    Fiscal Year
                                </label>
                                <Select value={String(selectedFiscalYear)} onValueChange={(value) => setSelectedFiscalYear(Number(value))}>
                                    <SelectTrigger id="fiscal-year" className="min-w-[180px] border-white/20 bg-white/10 text-white">
                                        <SelectValue placeholder="Select year" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {fiscalYears.map((year) => (
                                            <SelectItem key={year} value={String(year)}>
                                                FY {year} / {year + 1}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <Button type="button" variant="secondary" onClick={refresh} className="bg-white text-slate-900 hover:bg-slate-100">
                                <RefreshCcw className="mr-2 h-4 w-4" />
                                Refresh
                            </Button>
                        </div>
                    </div>
                </div>

                {error ? (
                    <Alert className="border-red-300 bg-red-50 text-red-900">
                        <AlertTriangle className="h-4 w-4" />
                        <AlertTitle>Dashboard load failed</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                ) : null}

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {summaryCards.map((card) => (
                        <Card key={card.title} className="overflow-hidden border-slate-200/70 shadow-sm">
                            <CardHeader className="pb-2">
                                <CardTitle className="text-muted-foreground text-sm font-medium">{card.title}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-3xl font-semibold tracking-tight">{card.value}</div>
                                <div
                                    className={`mt-3 h-1.5 rounded-full bg-gradient-to-r ${
                                        card.tone === 'emerald'
                                            ? 'from-teal-600 to-emerald-400'
                                            : card.tone === 'orange'
                                              ? 'from-orange-600 to-amber-400'
                                              : card.tone === 'blue'
                                                ? 'from-blue-600 to-cyan-400'
                                                : 'from-slate-500 to-slate-300'
                                    }`}
                                />
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <div>
                        {isLoading ? (
                            <DashboardLoadingCard title="12-Month Financial Trend" />
                        ) : financials ? (
                            <FinancialLineChart data={financials.months} />
                        ) : null}
                    </div>
                    <div>
                        {isLoading ? (
                            <DashboardLoadingCard title="Top Product Variants" />
                        ) : topProducts ? (
                            <TopProductsChart data={topProducts.data} />
                        ) : null}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function DashboardLoadingCard({ title }: { title: string }) {
    return (
        <Card className="overflow-hidden border-slate-200/70 shadow-sm">
            <CardHeader>
                <CardTitle className="text-muted-foreground text-sm font-medium">{title}</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="bg-muted/30 flex h-[320px] items-center justify-center rounded-2xl border border-dashed">
                    <div className="text-muted-foreground inline-flex items-center gap-2 text-sm">
                        <LoaderCircle className="h-4 w-4 animate-spin" />
                        Loading dashboard metrics...
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
