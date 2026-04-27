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

interface WarehouseOption {
    id: string;
    name: string;
}

interface PurchaseItemFormRow {
    variant_id: string;
    quantity: string;
    unit_cost: string;
}

interface PurchaseRecord {
    id: string;
    supplier_name: string | null;
    invoice_number: string | null;
    purchase_date: string | null;
    total_amount: string | null;
    status: 'POSTED' | 'VOIDED';
    notes: string | null;
    warehouse_id: string | null;
    item_count: number;
    items: Array<{
        variant_id: string;
        product_name: string | null;
        variant_label: string;
        quantity: string;
        unit_cost: string;
        total_cost: string;
    }>;
    created_at: string;
}

interface PurchasesPageProps {
    purchases: {
        data: PurchaseRecord[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    variantOptions: VariantOption[];
    warehouses: WarehouseOption[];
}

interface PurchaseFormData {
    supplier_name: string;
    invoice_number: string;
    notes: string;
    purchase_date: string;
    warehouse_id: string;
    items: PurchaseItemFormRow[];
}

interface PurchaseEditFormData {
    supplier_name: string;
    notes: string;
}

interface PurchaseReturnFormRow {
    variant_id: string;
    quantity: string;
}

interface PurchaseReturnFormData {
    warehouse_id: string;
    return_date: string;
    notes: string;
    items: PurchaseReturnFormRow[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Purchases',
        href: '/purchases',
    },
];

const emptyRow = (): PurchaseItemFormRow => ({
    variant_id: '',
    quantity: '',
    unit_cost: '',
});

export default function PurchasesIndex({ purchases, variantOptions, warehouses }: PurchasesPageProps) {
    const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
    const [editingPurchase, setEditingPurchase] = useState<PurchaseRecord | null>(null);
    const [returningPurchase, setReturningPurchase] = useState<PurchaseRecord | null>(null);

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<PurchaseFormData>({
        supplier_name: '',
        invoice_number: '',
        notes: '',
        purchase_date: new Date().toISOString().slice(0, 10),
        warehouse_id: '',
        items: [emptyRow()],
    });

    const editForm = useForm<PurchaseEditFormData>({
        supplier_name: '',
        notes: '',
    });

    const returnForm = useForm<PurchaseReturnFormData>({
        warehouse_id: '',
        return_date: new Date().toISOString().slice(0, 10),
        notes: '',
        items: [{ variant_id: '', quantity: '' }],
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

    const updateRow = (index: number, field: keyof PurchaseItemFormRow, value: string) => {
        const nextItems = data.items.map((row, currentIndex) => (currentIndex === index ? { ...row, [field]: value } : row));
        setData('items', nextItems);
    };

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        post(route('purchases.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                clearErrors();
                setData('purchase_date', new Date().toISOString().slice(0, 10));
                setData('warehouse_id', '');
                setData('notes', '');
                setData('items', [emptyRow()]);
                setIsCreateDialogOpen(false);
            },
        });
    };

    const openEditDialog = (purchase: PurchaseRecord) => {
        setEditingPurchase(purchase);
        editForm.clearErrors();
        editForm.setData({
            supplier_name: purchase.supplier_name ?? '',
            notes: purchase.notes ?? '',
        });
    };

    const submitEdit: FormEventHandler = (event) => {
        event.preventDefault();

        if (!editingPurchase) {
            return;
        }

        editForm.put(route('purchases.update', editingPurchase.id), {
            preserveScroll: true,
            onSuccess: () => {
                setEditingPurchase(null);
                editForm.clearErrors();
            },
        });
    };

    const voidPurchase = (purchase: PurchaseRecord) => {
        if (!window.confirm('This will reverse stock and financial impact. Continue to void this purchase?')) {
            return;
        }

        const reason = window.prompt('Void reason (optional)') ?? '';

        router.post(
            route('purchases.void', purchase.id),
            {
                reason: reason || null,
                void_date: new Date().toISOString().slice(0, 10),
            },
            {
                preserveScroll: true,
            },
        );
    };

    const openReturnDialog = (purchase: PurchaseRecord) => {
        setReturningPurchase(purchase);
        returnForm.clearErrors();
        returnForm.setData({
            warehouse_id: purchase.warehouse_id ?? '',
            return_date: new Date().toISOString().slice(0, 10),
            notes: '',
            items: [{ variant_id: purchase.items[0]?.variant_id ?? '', quantity: '' }],
        });
    };

    const addReturnRow = () => {
        returnForm.setData('items', [...returnForm.data.items, { variant_id: '', quantity: '' }]);
    };

    const removeReturnRow = (index: number) => {
        if (returnForm.data.items.length === 1) {
            return;
        }

        returnForm.setData(
            'items',
            returnForm.data.items.filter((_, currentIndex) => currentIndex !== index),
        );
    };

    const updateReturnRow = (index: number, field: keyof PurchaseReturnFormRow, value: string) => {
        returnForm.setData(
            'items',
            returnForm.data.items.map((row, currentIndex) => (currentIndex === index ? { ...row, [field]: value } : row)),
        );
    };

    const submitReturn: FormEventHandler = (event) => {
        event.preventDefault();

        if (!returningPurchase) {
            return;
        }

        returnForm.post(route('purchases.returns.store', returningPurchase.id), {
            preserveScroll: true,
            onSuccess: () => {
                setReturningPurchase(null);
                returnForm.clearErrors();
            },
        });
    };

    const resultStart = purchases.total === 0 ? 0 : (purchases.current_page - 1) * purchases.per_page + 1;
    const resultEnd = Math.min(purchases.current_page * purchases.per_page, purchases.total);

    const variantOptionsMemo = useMemo(() => variantOptions, [variantOptions]);
    const warehouseOptionsMemo = useMemo(() => warehouses, [warehouses]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Purchases" />

            <div className="space-y-6 p-4">
                <div className="rounded-xl border p-4 md:p-6">
                    <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div>
                            <h1 className="text-2xl font-semibold">Purchases</h1>
                            <p className="text-muted-foreground mt-1 text-sm">Create purchases with multiple items and track inventory layers.</p>
                        </div>

                        <Button onClick={() => setIsCreateDialogOpen(true)}>
                            <Plus className="mr-2 h-4 w-4" />
                            Make Purchase
                        </Button>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/40">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">Supplier</th>
                                <th className="px-4 py-3 text-left font-medium">Invoice</th>
                                <th className="px-4 py-3 text-left font-medium">Date</th>
                                <th className="px-4 py-3 text-left font-medium">Status</th>
                                <th className="px-4 py-3 text-left font-medium">Items</th>
                                <th className="px-4 py-3 text-right font-medium">Total</th>
                                <th className="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {purchases.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="text-muted-foreground px-4 py-8 text-center">
                                        No purchases yet.
                                    </td>
                                </tr>
                            ) : (
                                purchases.data.map((purchase) => (
                                    <tr key={purchase.id} className="border-t align-top">
                                        <td className="px-4 py-3 font-medium">{purchase.supplier_name ?? '-'}</td>
                                        <td className="px-4 py-3">{purchase.invoice_number ?? '-'}</td>
                                        <td className="px-4 py-3">{purchase.purchase_date ?? '-'}</td>
                                        <td className="px-4 py-3">
                                            <Badge variant={purchase.status === 'VOIDED' ? 'destructive' : 'outline'}>{purchase.status}</Badge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge variant="outline">{purchase.item_count} items</Badge>
                                            <div className="text-muted-foreground mt-2 space-y-1 text-xs">
                                                {purchase.items.map((item) => (
                                                    <div key={`${purchase.id}-${item.variant_id}`}>
                                                        {item.variant_label} | Qty {item.quantity} | Cost {item.unit_cost}
                                                    </div>
                                                ))}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-right">{purchase.total_amount ?? '-'}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-2">
                                                <Button type="button" size="sm" variant="outline" onClick={() => openEditDialog(purchase)}>
                                                    Edit
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => openReturnDialog(purchase)}
                                                    disabled={purchase.status !== 'POSTED'}
                                                >
                                                    Return
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="destructive"
                                                    onClick={() => voidPurchase(purchase)}
                                                    disabled={purchase.status !== 'POSTED'}
                                                >
                                                    Void
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between gap-3">
                    <p className="text-muted-foreground text-sm">
                        Showing {resultStart}-{resultEnd} of {purchases.total}
                    </p>
                </div>
            </div>

            <Dialog open={isCreateDialogOpen} onOpenChange={setIsCreateDialogOpen}>
                <DialogContent className="max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>Make Purchase</DialogTitle>
                        <DialogDescription>Add one or more items to record a purchase.</DialogDescription>
                    </DialogHeader>

                    <form className="space-y-4" onSubmit={submit}>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="supplier_name">Supplier Name</Label>
                                <Input
                                    id="supplier_name"
                                    value={data.supplier_name}
                                    onChange={(event) => setData('supplier_name', event.target.value)}
                                />
                                <InputError message={groupedErrors.supplier_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="invoice_number">Invoice Number</Label>
                                <Input
                                    id="invoice_number"
                                    value={data.invoice_number}
                                    onChange={(event) => setData('invoice_number', event.target.value)}
                                />
                                <InputError message={groupedErrors.invoice_number} />
                            </div>

                            <div className="grid gap-2 md:col-span-2">
                                <Label htmlFor="notes">Notes</Label>
                                <Input id="notes" value={data.notes} onChange={(event) => setData('notes', event.target.value)} />
                                <InputError message={groupedErrors.notes} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="purchase_date">Purchase Date</Label>
                                <Input
                                    id="purchase_date"
                                    type="date"
                                    value={data.purchase_date}
                                    onChange={(event) => setData('purchase_date', event.target.value)}
                                />
                                <InputError message={groupedErrors.purchase_date} />
                            </div>

                            <div className="grid gap-2">
                                <Label>Warehouse</Label>
                                <Select
                                    value={data.warehouse_id || '__none'}
                                    onValueChange={(value) => setData('warehouse_id', value === '__none' ? '' : value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select warehouse" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__none">Select warehouse</SelectItem>
                                        {warehouseOptionsMemo.map((warehouse) => (
                                            <SelectItem key={warehouse.id} value={warehouse.id}>
                                                {warehouse.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={groupedErrors.warehouse_id} />
                            </div>
                        </div>

                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-semibold">Purchase Items</h3>
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
                                        <Label>Unit Cost</Label>
                                        <Input
                                            type="number"
                                            step="0.0001"
                                            min="0"
                                            value={item.unit_cost}
                                            onChange={(event) => updateRow(index, 'unit_cost', event.target.value)}
                                        />
                                        <InputError message={groupedErrors[`items.${index}.unit_cost`]} />
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
                                Save Purchase
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={!!editingPurchase} onOpenChange={(open) => !open && setEditingPurchase(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Purchase</DialogTitle>
                        <DialogDescription>Only non-financial fields can be edited after posting.</DialogDescription>
                    </DialogHeader>

                    <form className="space-y-4" onSubmit={submitEdit}>
                        <div className="grid gap-2">
                            <Label htmlFor="edit-supplier-name">Supplier Name</Label>
                            <Input
                                id="edit-supplier-name"
                                value={editForm.data.supplier_name}
                                onChange={(event) => editForm.setData('supplier_name', event.target.value)}
                            />
                            <InputError message={editForm.errors.supplier_name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="edit-purchase-notes">Notes</Label>
                            <Input
                                id="edit-purchase-notes"
                                value={editForm.data.notes}
                                onChange={(event) => editForm.setData('notes', event.target.value)}
                            />
                            <InputError message={editForm.errors.notes} />
                        </div>

                        <DialogFooter>
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button type="submit" disabled={editForm.processing}>
                                {editForm.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                Save Changes
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={!!returningPurchase} onOpenChange={(open) => !open && setReturningPurchase(null)}>
                <DialogContent className="max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Return Purchase Items</DialogTitle>
                        <DialogDescription>This will reverse stock and financial impact for selected quantities.</DialogDescription>
                    </DialogHeader>

                    <form className="space-y-4" onSubmit={submitReturn}>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="grid gap-2">
                                <Label>Warehouse</Label>
                                <Select
                                    value={returnForm.data.warehouse_id || '__none'}
                                    onValueChange={(value) => returnForm.setData('warehouse_id', value === '__none' ? '' : value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select warehouse" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__none">Select warehouse</SelectItem>
                                        {warehouseOptionsMemo.map((warehouse) => (
                                            <SelectItem key={warehouse.id} value={warehouse.id}>
                                                {warehouse.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={returnForm.errors.warehouse_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="purchase-return-date">Return Date</Label>
                                <Input
                                    id="purchase-return-date"
                                    type="date"
                                    value={returnForm.data.return_date}
                                    onChange={(event) => returnForm.setData('return_date', event.target.value)}
                                />
                                <InputError message={returnForm.errors.return_date} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="purchase-return-notes">Notes</Label>
                                <Input
                                    id="purchase-return-notes"
                                    value={returnForm.data.notes}
                                    onChange={(event) => returnForm.setData('notes', event.target.value)}
                                />
                                <InputError message={returnForm.errors.notes} />
                            </div>
                        </div>

                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-semibold">Return Items</h3>
                                <Button type="button" variant="outline" size="sm" onClick={addReturnRow}>
                                    Add Row
                                </Button>
                            </div>

                            {returnForm.data.items.map((item, index) => (
                                <div key={index} className="grid gap-3 rounded-lg border p-3 md:grid-cols-[2fr_1fr_auto]">
                                    <div className="grid gap-2">
                                        <Label>Variant</Label>
                                        <Select
                                            value={item.variant_id || '__none'}
                                            onValueChange={(value) => updateReturnRow(index, 'variant_id', value === '__none' ? '' : value)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select variant" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="__none">Select variant</SelectItem>
                                                {(returningPurchase?.items ?? []).map((purchaseItem) => (
                                                    <SelectItem
                                                        key={`${purchaseItem.variant_id}-${purchaseItem.variant_label}`}
                                                        value={purchaseItem.variant_id}
                                                    >
                                                        {purchaseItem.variant_label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={returnForm.errors[`items.${index}.variant_id`]} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label>Quantity</Label>
                                        <Input
                                            type="number"
                                            step="0.0001"
                                            min="0"
                                            value={item.quantity}
                                            onChange={(event) => updateReturnRow(index, 'quantity', event.target.value)}
                                        />
                                        <InputError message={returnForm.errors[`items.${index}.quantity`]} />
                                    </div>

                                    <div className="flex items-end">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => removeReturnRow(index)}
                                            disabled={returnForm.data.items.length === 1}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                            <InputError message={returnForm.errors.items} />
                        </div>

                        <DialogFooter>
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button type="submit" disabled={returnForm.processing}>
                                {returnForm.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                Save Return
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
