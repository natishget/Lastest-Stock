import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { LoaderCircle, Search } from 'lucide-react';
import { FormEventHandler, useMemo, useState } from 'react';

type Origin = 'LOCAL' | 'IMPORTED';

interface ManagedProduct {
    id: string;
    name: string;
    base_unit: string | null;
    variant_count: number;
    origins: string[];
    colors: string[];
    skus: string[];
    thicknesses: string[];
    sizes: string[];
    created_at: string;
}

interface ProductsPageProps {
    products: {
        data: ManagedProduct[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        search: string;
        origin: '' | Origin;
        color: string;
    };
    origins: Origin[];
    colors: string[];
}

interface ProductFormData {
    name: string;
    base_unit: string;
}

interface VariantFormData {
    color: string;
    origin: '' | Origin;
    sku: string;
    thickness: string;
    size: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Product Management',
        href: '/products',
    },
];

function formatValues(values: string[]): string {
    if (values.length === 0) {
        return '-';
    }

    return values.join(', ');
}

function ValueBadges({ values }: { values: string[] }) {
    if (values.length === 0) {
        return <span className="text-muted-foreground">-</span>;
    }

    return (
        <div className="flex flex-wrap gap-1.5">
            {values.map((value) => (
                <Badge key={value} variant="secondary" className="rounded-md px-2 py-0.5 text-[11px] font-medium tracking-wide uppercase">
                    {value}
                </Badge>
            ))}
        </div>
    );
}

export default function ProductManagementPage({ products, filters, origins, colors }: ProductsPageProps) {
    const [searchTerm, setSearchTerm] = useState(filters.search ?? '');
    const [originFilter, setOriginFilter] = useState<Origin | ''>(filters.origin ?? '');
    const [colorFilter, setColorFilter] = useState(filters.color ?? '');
    const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
    const [editingProduct, setEditingProduct] = useState<ManagedProduct | null>(null);
    const [variantProduct, setVariantProduct] = useState<ManagedProduct | null>(null);
    const [deletingProduct, setDeletingProduct] = useState<ManagedProduct | null>(null);

    const createForm = useForm<ProductFormData>({
        name: '',
        base_unit: '',
    });

    const editForm = useForm<ProductFormData>({
        name: '',
        base_unit: '',
    });

    const variantForm = useForm<VariantFormData>({
        color: '',
        origin: '',
        sku: '',
        thickness: '',
        size: '',
    });

    const searchProducts: FormEventHandler = (event) => {
        event.preventDefault();

        router.get(
            route('products.index'),
            {
                search: searchTerm || undefined,
                origin: originFilter || undefined,
                color: colorFilter || undefined,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    };

    const clearFilters = () => {
        setSearchTerm('');
        setOriginFilter('');
        setColorFilter('');

        router.get(route('products.index'), {}, { preserveScroll: true, preserveState: true, replace: true });
    };

    const goToPage = (page: number) => {
        if (page < 1 || page > products.last_page) {
            return;
        }

        router.get(
            route('products.index'),
            {
                page,
                search: filters.search || undefined,
                origin: filters.origin || undefined,
                color: filters.color || undefined,
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const submitCreate: FormEventHandler = (event) => {
        event.preventDefault();

        createForm.post(route('products.store'), {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset();
                setIsCreateDialogOpen(false);
            },
        });
    };

    const openEditDialog = (product: ManagedProduct) => {
        setEditingProduct(product);
        editForm.clearErrors();
        editForm.setData({
            name: product.name,
            base_unit: product.base_unit ?? '',
        });
    };

    const submitEdit: FormEventHandler = (event) => {
        event.preventDefault();

        if (!editingProduct) {
            return;
        }

        editForm.put(route('products.update', editingProduct.id), {
            preserveScroll: true,
            onSuccess: () => {
                setEditingProduct(null);
                editForm.clearErrors();
            },
        });
    };

    const openVariantDialog = (product: ManagedProduct) => {
        setVariantProduct(product);
        variantForm.clearErrors();
        variantForm.reset();
    };

    const submitVariant: FormEventHandler = (event) => {
        event.preventDefault();

        if (!variantProduct) {
            return;
        }

        variantForm.post(route('products.variants.store', variantProduct.id), {
            preserveScroll: true,
            onSuccess: () => {
                setVariantProduct(null);
                variantForm.reset();
                variantForm.clearErrors();
            },
        });
    };

    const confirmDelete = () => {
        if (!deletingProduct) {
            return;
        }

        router.delete(route('products.destroy', deletingProduct.id), {
            preserveScroll: true,
            onSuccess: () => setDeletingProduct(null),
        });
    };

    const resultStart = products.total === 0 ? 0 : (products.current_page - 1) * products.per_page + 1;
    const resultEnd = Math.min(products.current_page * products.per_page, products.total);

    const originOptions = useMemo(() => [...origins], [origins]);
    const colorOptions = useMemo(() => [...colors], [colors]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Product Management" />

            <div className="space-y-6 p-4">
                <div className="rounded-xl border p-4 md:p-6">
                    <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div>
                            <h1 className="text-2xl font-semibold">Product Management</h1>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Manage products, review their variants, and filter by origin or color.
                            </p>
                        </div>

                        <Button onClick={() => setIsCreateDialogOpen(true)}>Add Product</Button>
                    </div>

                    <form onSubmit={searchProducts} className="mt-6 grid gap-3 md:grid-cols-[1fr_180px_180px_auto_auto]">
                        <div className="grid gap-2">
                            <Label htmlFor="search">Search by name</Label>
                            <div className="relative">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                <Input
                                    id="search"
                                    value={searchTerm}
                                    onChange={(event) => setSearchTerm(event.target.value)}
                                    className="pl-9"
                                    placeholder="Search products"
                                />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="origin">Origin</Label>
                            <Select
                                value={originFilter || '__all'}
                                onValueChange={(value) => setOriginFilter(value === '__all' ? '' : (value as Origin))}
                            >
                                <SelectTrigger id="origin">
                                    <SelectValue placeholder="All origins" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__all">All origins</SelectItem>
                                    {originOptions.map((origin) => (
                                        <SelectItem key={origin} value={origin}>
                                            {origin}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="color">Color</Label>
                            <Select value={colorFilter || '__all'} onValueChange={(value) => setColorFilter(value === '__all' ? '' : value)}>
                                <SelectTrigger id="color">
                                    <SelectValue placeholder="All colors" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__all">All colors</SelectItem>
                                    {colorOptions.map((color) => (
                                        <SelectItem key={color} value={color}>
                                            {color}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <Button type="submit" className="self-end">
                            Apply
                        </Button>
                        <Button type="button" variant="outline" className="self-end" onClick={clearFilters}>
                            Reset
                        </Button>
                    </form>
                </div>

                <div className="overflow-hidden rounded-xl border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/40">
                                <tr>
                                    <th className="px-4 py-3 text-left font-medium">Name</th>
                                    <th className="px-4 py-3 text-left font-medium">Base Unit</th>
                                    <th className="px-4 py-3 text-left font-medium">Variants</th>
                                    <th className="px-4 py-3 text-left font-medium">Origin</th>
                                    <th className="px-4 py-3 text-left font-medium">Color</th>
                                    <th className="px-4 py-3 text-left font-medium">SKU</th>
                                    <th className="px-4 py-3 text-left font-medium">Thickness</th>
                                    <th className="px-4 py-3 text-left font-medium">Size</th>
                                    <th className="px-4 py-3 text-left font-medium">Created</th>
                                    <th className="px-4 py-3 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {products.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={10} className="text-muted-foreground px-4 py-8 text-center">
                                            No products found.
                                        </td>
                                    </tr>
                                ) : (
                                    products.data.map((product) => (
                                        <tr key={product.id} className="border-t align-top">
                                            <td className="px-4 py-3 font-medium">{product.name}</td>
                                            <td className="px-4 py-3">{product.base_unit ?? '-'}</td>
                                            <td className="px-4 py-3">
                                                <Badge variant="outline" className="rounded-md px-2.5 py-0.5 text-[11px]">
                                                    {product.variant_count} total
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3">
                                                <ValueBadges values={product.origins} />
                                            </td>
                                            <td className="px-4 py-3">
                                                <ValueBadges values={product.colors} />
                                            </td>
                                            <td className="px-4 py-3">{formatValues(product.skus)}</td>
                                            <td className="px-4 py-3">{formatValues(product.thicknesses)}</td>
                                            <td className="px-4 py-3">{formatValues(product.sizes)}</td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                {new Date(product.created_at).toLocaleDateString(undefined, {
                                                    year: 'numeric',
                                                    month: 'short',
                                                    day: 'numeric',
                                                })}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex justify-end gap-2">
                                                    <Button type="button" variant="secondary" size="sm" onClick={() => openVariantDialog(product)}>
                                                        Add Variant
                                                    </Button>
                                                    <Button type="button" variant="outline" size="sm" onClick={() => openEditDialog(product)}>
                                                        Edit
                                                    </Button>
                                                    <Button type="button" variant="destructive" size="sm" onClick={() => setDeletingProduct(product)}>
                                                        Delete
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
                    <p className="text-muted-foreground text-sm">
                        Showing {resultStart}-{resultEnd} of {products.total}
                    </p>

                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => goToPage(products.current_page - 1)}
                            disabled={products.current_page <= 1}
                        >
                            Previous
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => goToPage(products.current_page + 1)}
                            disabled={products.current_page >= products.last_page}
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </div>

            <Dialog open={isCreateDialogOpen} onOpenChange={setIsCreateDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add New Product</DialogTitle>
                        <DialogDescription>Create a new product record.</DialogDescription>
                    </DialogHeader>

                    <form className="space-y-4" onSubmit={submitCreate}>
                        <div className="grid gap-2">
                            <Label htmlFor="create-name">Name</Label>
                            <Input
                                id="create-name"
                                value={createForm.data.name}
                                onChange={(event) => createForm.setData('name', event.target.value)}
                                required
                            />
                            <InputError message={createForm.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="create-base-unit">Base Unit</Label>
                            <Input
                                id="create-base-unit"
                                value={createForm.data.base_unit}
                                onChange={(event) => createForm.setData('base_unit', event.target.value)}
                                placeholder="Piece, meter, box..."
                            />
                            <InputError message={createForm.errors.base_unit} />
                        </div>

                        <DialogFooter>
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button type="submit" disabled={createForm.processing}>
                                {createForm.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                Save Product
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={!!editingProduct} onOpenChange={(open) => !open && setEditingProduct(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Product</DialogTitle>
                        <DialogDescription>Update the product name and base unit.</DialogDescription>
                    </DialogHeader>

                    <form className="space-y-4" onSubmit={submitEdit}>
                        <div className="grid gap-2">
                            <Label htmlFor="edit-name">Name</Label>
                            <Input
                                id="edit-name"
                                value={editForm.data.name}
                                onChange={(event) => editForm.setData('name', event.target.value)}
                                required
                            />
                            <InputError message={editForm.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="edit-base-unit">Base Unit</Label>
                            <Input
                                id="edit-base-unit"
                                value={editForm.data.base_unit}
                                onChange={(event) => editForm.setData('base_unit', event.target.value)}
                                placeholder="Piece, meter, box..."
                            />
                            <InputError message={editForm.errors.base_unit} />
                        </div>

                        <DialogFooter>
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button type="submit" disabled={editForm.processing}>
                                {editForm.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                Update Product
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={!!variantProduct} onOpenChange={(open) => !open && setVariantProduct(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add Product Variant</DialogTitle>
                        <DialogDescription>Add a new variant for {variantProduct?.name}.</DialogDescription>
                    </DialogHeader>

                    <form className="space-y-4" onSubmit={submitVariant}>
                        <div className="grid gap-2">
                            <Label htmlFor="variant-origin">Origin</Label>
                            <Select
                                value={variantForm.data.origin || '__none'}
                                onValueChange={(value) => variantForm.setData('origin', value === '__none' ? '' : (value as Origin))}
                            >
                                <SelectTrigger id="variant-origin">
                                    <SelectValue placeholder="Select origin" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__none">None</SelectItem>
                                    <SelectItem value="LOCAL">LOCAL</SelectItem>
                                    <SelectItem value="IMPORTED">IMPORTED</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={variantForm.errors.origin} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="variant-color">Color</Label>
                            <Input
                                id="variant-color"
                                value={variantForm.data.color}
                                onChange={(event) => variantForm.setData('color', event.target.value)}
                                placeholder="Blue"
                            />
                            <InputError message={variantForm.errors.color} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="variant-sku">SKU</Label>
                            <Input
                                id="variant-sku"
                                value={variantForm.data.sku}
                                onChange={(event) => variantForm.setData('sku', event.target.value)}
                                placeholder="SKU-001"
                            />
                            <InputError message={variantForm.errors.sku} />
                        </div>

                        <div className="grid gap-2 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="variant-thickness">Thickness</Label>
                                <Input
                                    id="variant-thickness"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={variantForm.data.thickness}
                                    onChange={(event) => variantForm.setData('thickness', event.target.value)}
                                    placeholder="1.25"
                                />
                                <InputError message={variantForm.errors.thickness} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="variant-size">Size</Label>
                                <Input
                                    id="variant-size"
                                    value={variantForm.data.size}
                                    onChange={(event) => variantForm.setData('size', event.target.value)}
                                    placeholder="XL"
                                />
                                <InputError message={variantForm.errors.size} />
                            </div>
                        </div>

                        <DialogFooter>
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button type="submit" disabled={variantForm.processing}>
                                {variantForm.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                Save Variant
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={!!deletingProduct} onOpenChange={(open) => !open && setDeletingProduct(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Product</DialogTitle>
                        <DialogDescription>Are you sure you want to delete {deletingProduct?.name}? This action cannot be undone.</DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button type="button" variant="destructive" onClick={confirmDelete}>
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
