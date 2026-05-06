import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useEffect, useMemo, useState } from 'react';

interface WarehouseRecord {
    id: string;
    name: string;
    location: string | null;
}

interface WarehouseStockRecord {
    variant_id: string;
    product_name: string;
    color: string | null;
    origin: string | null;
    total_stock: string;
}

interface WarehouseFormData {
    name: string;
    location: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Warehouses',
        href: '/warehouses',
    },
];

const csrfToken = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';

async function requestJson<T>(url: string, options: RequestInit = {}): Promise<T> {
    const response = await fetch(url, {
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            ...(options.headers ?? {}),
        },
        credentials: 'same-origin',
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw payload;
    }

    return payload as T;
}

export default function WarehousesIndex() {
    const { auth } = usePage<{ auth: { user: { role: 'ADMIN' | 'SALES' | 'AUDITOR' } } }>().props;
    const canManageWarehouses = auth.user.role === 'ADMIN';

    const [warehouses, setWarehouses] = useState<WarehouseRecord[]>([]);
    const [isLoadingWarehouses, setIsLoadingWarehouses] = useState(true);
    const [warehouseLoadError, setWarehouseLoadError] = useState('');

    const [isFormDialogOpen, setIsFormDialogOpen] = useState(false);
    const [editingWarehouse, setEditingWarehouse] = useState<WarehouseRecord | null>(null);
    const [isSubmittingForm, setIsSubmittingForm] = useState(false);
    const [formData, setFormData] = useState<WarehouseFormData>({
        name: '',
        location: '',
    });
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});

    const [stockWarehouse, setStockWarehouse] = useState<WarehouseRecord | null>(null);
    const [stockRows, setStockRows] = useState<WarehouseStockRecord[]>([]);
    const [isLoadingStock, setIsLoadingStock] = useState(false);
    const [stockLoadError, setStockLoadError] = useState('');

    const [deleteError, setDeleteError] = useState('');
    const [deletingWarehouseId, setDeletingWarehouseId] = useState<string | null>(null);

    const fetchWarehouses = async () => {
        setIsLoadingWarehouses(true);
        setWarehouseLoadError('');

        try {
            const response = await requestJson<{ data: WarehouseRecord[] }>('/api/warehouses');
            setWarehouses(response.data ?? []);
        } catch {
            setWarehouseLoadError('Unable to load warehouses right now.');
        } finally {
            setIsLoadingWarehouses(false);
        }
    };

    useEffect(() => {
        void fetchWarehouses();
    }, []);

    const openCreateDialog = () => {
        setEditingWarehouse(null);
        setFormData({ name: '', location: '' });
        setFormErrors({});
        setIsFormDialogOpen(true);
    };

    const openEditDialog = (warehouse: WarehouseRecord) => {
        setEditingWarehouse(warehouse);
        setFormData({
            name: warehouse.name,
            location: warehouse.location ?? '',
        });
        setFormErrors({});
        setIsFormDialogOpen(true);
    };

    const submitForm: FormEventHandler = async (event) => {
        event.preventDefault();
        setIsSubmittingForm(true);
        setFormErrors({});

        const url = editingWarehouse ? `/api/warehouses/${editingWarehouse.id}` : '/api/warehouses';
        const method = editingWarehouse ? 'PUT' : 'POST';

        try {
            await requestJson(url, {
                method,
                body: JSON.stringify({
                    name: formData.name,
                    location: formData.location || null,
                }),
            });

            setIsFormDialogOpen(false);
            await fetchWarehouses();
        } catch (error) {
            const response = error as { errors?: Record<string, string[]> };

            if (response.errors) {
                const nextErrors: Record<string, string> = {};

                Object.entries(response.errors).forEach(([key, messages]) => {
                    nextErrors[key] = messages[0] ?? 'Invalid value.';
                });

                setFormErrors(nextErrors);
            }
        } finally {
            setIsSubmittingForm(false);
        }
    };

    const openStockDialog = async (warehouse: WarehouseRecord) => {
        setStockWarehouse(warehouse);
        setIsLoadingStock(true);
        setStockRows([]);
        setStockLoadError('');

        try {
            const response = await requestJson<{ data: WarehouseStockRecord[] }>(`/api/warehouses/${warehouse.id}/stock`);
            setStockRows(response.data ?? []);
        } catch {
            setStockLoadError('Unable to load stock for this warehouse.');
        } finally {
            setIsLoadingStock(false);
        }
    };

    const removeWarehouse = async (warehouse: WarehouseRecord) => {
        const confirmed = window.confirm(`Delete warehouse "${warehouse.name}"?`);

        if (!confirmed) {
            return;
        }

        setDeleteError('');
        setDeletingWarehouseId(warehouse.id);

        try {
            await requestJson(`/api/warehouses/${warehouse.id}`, {
                method: 'DELETE',
            });

            await fetchWarehouses();
        } catch (error) {
            const response = error as { errors?: Record<string, string[]> };
            setDeleteError(response.errors?.warehouse?.[0] ?? 'Unable to delete warehouse.');
        } finally {
            setDeletingWarehouseId(null);
        }
    };

    const totalWarehouses = useMemo(() => warehouses.length, [warehouses]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Warehouses" />

            <div className="space-y-6 p-4">
                <div className="rounded-xl border p-4 md:p-6">
                    <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div>
                            <h1 className="text-2xl font-semibold">Warehouse Management</h1>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Manage warehouses and inspect live stock from inventory transactions.
                            </p>
                        </div>

                        {canManageWarehouses ? <Button onClick={openCreateDialog}>Add Warehouse</Button> : null}
                    </div>
                </div>

                {deleteError ? <div className="rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">{deleteError}</div> : null}

                <div className="overflow-hidden rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/40">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">Name</th>
                                <th className="px-4 py-3 text-left font-medium">Location</th>
                                {canManageWarehouses ? <th className="px-4 py-3 text-right font-medium">Actions</th> : null}
                            </tr>
                        </thead>
                        <tbody>
                            {isLoadingWarehouses ? (
                                <tr>
                                    <td colSpan={3} className="px-4 py-8 text-center">
                                        <div className="inline-flex items-center gap-2 text-sm">
                                            <LoaderCircle className="h-4 w-4 animate-spin" />
                                            Loading warehouses...
                                        </div>
                                    </td>
                                </tr>
                            ) : warehouseLoadError ? (
                                <tr>
                                    <td colSpan={3} className="px-4 py-8 text-center text-red-600">
                                        {warehouseLoadError}
                                    </td>
                                </tr>
                            ) : warehouses.length === 0 ? (
                                <tr>
                                    <td colSpan={3} className="text-muted-foreground px-4 py-8 text-center">
                                        No warehouses found.
                                    </td>
                                </tr>
                            ) : (
                                warehouses.map((warehouse) => (
                                    <tr key={warehouse.id} className="border-t align-top">
                                        <td className="px-4 py-3 font-medium">{warehouse.name}</td>
                                        <td className="px-4 py-3">{warehouse.location || '-'}</td>
                                        {canManageWarehouses ? (
                                            <td className="px-4 py-3">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        type="button"
                                                        variant="secondary"
                                                        size="sm"
                                                        onClick={() => void openStockDialog(warehouse)}
                                                    >
                                                        View
                                                    </Button>
                                                    <Button type="button" variant="outline" size="sm" onClick={() => openEditDialog(warehouse)}>
                                                        Edit
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="destructive"
                                                        size="sm"
                                                        disabled={deletingWarehouseId === warehouse.id}
                                                        onClick={() => void removeWarehouse(warehouse)}
                                                    >
                                                        {deletingWarehouseId === warehouse.id ? 'Deleting...' : 'Delete'}
                                                    </Button>
                                                </div>
                                            </td>
                                        ) : (
                                            <td className="px-4 py-3 text-right">
                                                <Button type="button" variant="secondary" size="sm" onClick={() => void openStockDialog(warehouse)}>
                                                    View
                                                </Button>
                                            </td>
                                        )}
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <p className="text-muted-foreground text-sm">Total warehouses: {totalWarehouses}</p>
            </div>

            <Dialog open={isFormDialogOpen} onOpenChange={setIsFormDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editingWarehouse ? 'Edit Warehouse' : 'Create Warehouse'}</DialogTitle>
                        <DialogDescription>Set warehouse details used by inventory transactions and stock tracking.</DialogDescription>
                    </DialogHeader>

                    <form className="space-y-4" onSubmit={submitForm}>
                        <div className="grid gap-2">
                            <Label htmlFor="warehouse_name">Name</Label>
                            <Input
                                id="warehouse_name"
                                value={formData.name}
                                onChange={(event) => setFormData((current) => ({ ...current, name: event.target.value }))}
                            />
                            <InputError message={formErrors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="warehouse_location">Location</Label>
                            <textarea
                                id="warehouse_location"
                                value={formData.location}
                                onChange={(event) => setFormData((current) => ({ ...current, location: event.target.value }))}
                                className="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring min-h-24 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                                placeholder="Warehouse address or description"
                            />
                            <InputError message={formErrors.location} />
                        </div>

                        <DialogFooter>
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button type="submit" disabled={isSubmittingForm}>
                                {isSubmittingForm && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                {editingWarehouse ? 'Save Changes' : 'Create Warehouse'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={stockWarehouse !== null} onOpenChange={(open) => !open && setStockWarehouse(null)}>
                <DialogContent className="max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>Warehouse Stock</DialogTitle>
                        <DialogDescription>{stockWarehouse ? `Current stock in ${stockWarehouse.name}.` : 'Stock details.'}</DialogDescription>
                    </DialogHeader>

                    <div className="overflow-hidden rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/40">
                                <tr>
                                    <th className="px-4 py-3 text-left font-medium">Product Name</th>
                                    <th className="px-4 py-3 text-left font-medium">Color</th>
                                    <th className="px-4 py-3 text-left font-medium">Origin</th>
                                    <th className="px-4 py-3 text-right font-medium">Available Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                {isLoadingStock ? (
                                    <tr>
                                        <td colSpan={4} className="px-4 py-8 text-center">
                                            <div className="inline-flex items-center gap-2 text-sm">
                                                <LoaderCircle className="h-4 w-4 animate-spin" />
                                                Loading stock...
                                            </div>
                                        </td>
                                    </tr>
                                ) : stockLoadError ? (
                                    <tr>
                                        <td colSpan={4} className="px-4 py-8 text-center text-red-600">
                                            {stockLoadError}
                                        </td>
                                    </tr>
                                ) : stockRows.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="text-muted-foreground px-4 py-8 text-center">
                                            No stock found in this warehouse.
                                        </td>
                                    </tr>
                                ) : (
                                    stockRows.map((stockRow) => (
                                        <tr key={stockRow.variant_id} className="border-t">
                                            <td className="px-4 py-3">{stockRow.product_name}</td>
                                            <td className="px-4 py-3">{stockRow.color || '-'}</td>
                                            <td className="px-4 py-3">{stockRow.origin || '-'}</td>
                                            <td className="px-4 py-3 text-right font-medium">{stockRow.total_stock}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
