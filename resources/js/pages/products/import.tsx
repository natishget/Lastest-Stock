import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface ImportFormData {
    products_json: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Product Management',
        href: '/products',
    },
    {
        title: 'Import JSON',
        href: '/products/import',
    },
];

const exampleJson = `[
  {
    "name": "Product Name",
    "unit": "Piece"
  },
  {
    "name": "Another Product",
    "unit": "Box"
  }
]`;

export default function ProductImportPage() {
    const form = useForm<ImportFormData>({
        products_json: exampleJson,
    });

    const submitImport: FormEventHandler = (event) => {
        event.preventDefault();

        form.post(route('products.import.store'), {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Import Products JSON" />

            <div className="space-y-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Import Products from JSON</CardTitle>
                        <CardDescription>
                            Paste a JSON array of products. Only <span className="font-medium">name</span> and{' '}
                            <span className="font-medium">unit</span>
                            fields are accepted, and each entry is validated before anything is written to the database.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form className="space-y-4" onSubmit={submitImport}>
                            <div className="grid gap-2">
                                <Label htmlFor="products_json">Products JSON</Label>
                                <textarea
                                    id="products_json"
                                    value={form.data.products_json}
                                    onChange={(event) => form.setData('products_json', event.target.value)}
                                    rows={18}
                                    spellCheck={false}
                                    className="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                                    placeholder={exampleJson}
                                />
                                <InputError message={form.errors.products_json} />
                            </div>

                            <div className="bg-muted/40 text-muted-foreground rounded-lg border p-4 text-sm">
                                The import is atomic. If one item is malformed, has unsupported fields, or already exists, nothing is saved.
                            </div>

                            <div className="flex flex-wrap gap-2">
                                <Button type="submit" disabled={form.processing}>
                                    {form.processing ? 'Importing...' : 'Import Products'}
                                </Button>
                                <Button type="button" variant="outline" onClick={() => form.setData('products_json', exampleJson)}>
                                    Reset Example
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
