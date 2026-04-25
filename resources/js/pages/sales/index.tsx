import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useMemo, useState } from 'react';

interface VariantOption {
    id: string;
    label: string;
}

interface SaleItemFormRow {
    variant_id: string;
    quantity: string;
    selling_price: string;
}

interface SaleRecord {
    id: string;
    customer_name: string | null;
    sale_date: string | null;
    total_amount: string | null;
    item_count: number;
    items: Array<{
        variant_id: string;
        product_name: string | null;
        variant_label: string;
        quantity: string;
        selling_price: string;
        total_price: string;
    }>;
    created_at: string;
}

interface SalesPageProps {
    sales: {
        data: SaleRecord[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    variantOptions: VariantOption[];
    availableQuantities: Record<string, string>;
}

interface SaleFormData {
    customer_name: string;
    sale_date: string;
    items: SaleItemFormRow[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Sales',
        href: '/sales',
    },
];

const emptyRow = (): SaleItemFormRow => ({
    variant_id: '',
    quantity: '',
    selling_price: '',
});

export default function SalesIndex({ sales, variantOptions, availableQuantities }: SalesPageProps) {
    const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<SaleFormData>({
        customer_name: '',
        sale_date: new Date().toISOString().slice(0, 10),
        items: [emptyRow()],
    });

    const groupedErrors = errors as Record<string, string | undefined>;

    const addRow = () => {
        setData('items', [...data.items, emptyRow()]);
    };

    const removeRow = (index: number) => {
        if (data.items.length === 1) {
            return;
        }

        setData(
            'items',
            data.items.filter((_, currentIndex) => currentIndex !== index),
        );
    };

    const updateRow = (index: number, field: keyof SaleItemFormRow, value: string) => {
        const nextItems = data.items.map((row, currentIndex) => (currentIndex === index ? { ...row, [field]: value } : row));
        setData('items', nextItems);
    };

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        post(route('sales.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                clearErrors();
                setData('sale_date', new Date().toISOString().slice(0, 10));
                setData('items', [emptyRow()]);
                setIsCreateDialogOpen(false);
            },
        });
    };

    const resultStart = sales.total === 0 ? 0 : (sales.current_page - 1) * sales.per_page + 1;
    const resultEnd = Math.min(sales.current_page * sales.per_page, sales.total);

    const variantOptionsMemo = useMemo(() => variantOptions, [variantOptions]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sales" />

            <div className="space-y-6 p-4">
                <div className="rounded-xl border p-4 md:p-6">
                    <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div>
                            <h1 className="text-2xl font-semibold">Sales</h1>
                            <p className="text-muted-foreground mt-1 text-sm">Create sales with multiple items and consume inventory FIFO layers.</p>
                        </div>

                        <Button onClick={() => setIsCreateDialogOpen(true)}>
                            <Plus className="mr-2 h-4 w-4" />
                            Make Sale
                        </Button>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/40">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">Customer</th>
                                <th className="px-4 py-3 text-left font-medium">Date</th>
                                <th className="px-4 py-3 text-left font-medium">Items</th>
                                <th className="px-4 py-3 text-right font-medium">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {sales.data.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="text-muted-foreground px-4 py-8 text-center">
                                        No sales yet.
                                    </td>
                                </tr>
                            ) : (
                                sales.data.map((sale) => (
                                    <tr key={sale.id} className="border-t align-top">
                                        <td className="px-4 py-3 font-medium">{sale.customer_name ?? '-'}</td>
                                        <td className="px-4 py-3">{sale.sale_date ?? '-'}</td>
                                        <td className="px-4 py-3">
                                            <Badge variant="outline">{sale.item_count} items</Badge>
                                            <div className="text-muted-foreground mt-2 space-y-1 text-xs">
                                                {sale.items.map((item) => (
                                                    <div key={`${sale.id}-${item.variant_id}`}>
                                                        {item.variant_label} | Qty {item.quantity} | Sell {item.selling_price}
                                                    </div>
                                                ))}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-right">{sale.total_amount ?? '-'}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between gap-3">
                    <p className="text-muted-foreground text-sm">
                        Showing {resultStart}-{resultEnd} of {sales.total}
                    </p>
                </div>
            </div>

            <Dialog open={isCreateDialogOpen} onOpenChange={setIsCreateDialogOpen}>
                <DialogContent className="max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>Make Sale</DialogTitle>
                        <DialogDescription>Add one or more items to record a sale.</DialogDescription>
                    </DialogHeader>

                    <form className="space-y-4" onSubmit={submit}>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="customer_name">Customer Name</Label>
                                <Input
                                    id="customer_name"
                                    value={data.customer_name}
                                    onChange={(event) => setData('customer_name', event.target.value)}
                                />
                                <InputError message={groupedErrors.customer_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="sale_date">Sale Date</Label>
                                <Input
                                    id="sale_date"
                                    type="date"
                                    value={data.sale_date}
                                    onChange={(event) => setData('sale_date', event.target.value)}
                                />
                                <InputError message={groupedErrors.sale_date} />
                            </div>
                        </div>

                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-semibold">Sale Items</h3>
                                <Button type="button" variant="outline" size="sm" onClick={addRow}>
                                    Add Row
                                </Button>
                            </div>

                            {data.items.map((item, index) => (
                                <div key={index} className="grid gap-3 rounded-lg border p-3 md:grid-cols-[2fr_1fr_1fr_auto]">
                                    <div className="grid gap-2">
                                        <Label>Variant</Label>
                                        <Select
                                            value={item.variant_id || '__none'}
                                            onValueChange={(value) => updateRow(index, 'variant_id', value === '__none' ? '' : value)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select variant" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="__none">Select variant</SelectItem>
                                                {variantOptionsMemo.map((option) => (
                                                    <SelectItem key={option.id} value={option.id}>
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <p className="text-muted-foreground text-xs">Available: {availableQuantities[item.variant_id] ?? '0'}</p>
                                        <InputError message={groupedErrors[`items.${index}.variant_id`]} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label>Quantity</Label>
                                        <Input
                                            type="number"
                                            step="0.0001"
                                            min="0"
                                            value={item.quantity}
                                            onChange={(event) => updateRow(index, 'quantity', event.target.value)}
                                        />
                                        <InputError message={groupedErrors[`items.${index}.quantity`]} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label>Selling Price</Label>
                                        <Input
                                            type="number"
                                            step="0.0001"
                                            min="0"
                                            value={item.selling_price}
                                            onChange={(event) => updateRow(index, 'selling_price', event.target.value)}
                                        />
                                        <InputError message={groupedErrors[`items.${index}.selling_price`]} />
                                    </div>

                                    <div className="flex items-end">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => removeRow(index)}
                                            disabled={data.items.length === 1}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            ))}

                            <InputError message={groupedErrors.items} />
                        </div>

                        <DialogFooter>
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button type="submit" disabled={processing}>
                                {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                Save Sale
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
