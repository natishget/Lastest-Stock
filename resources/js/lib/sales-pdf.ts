import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

export type SalesDocumentKind = 'INVOICE' | 'QUOTATION';

export interface SalesDocumentItem {
    name: string;
    quantity: number;
    unitPrice: number;
    totalPrice: number;
}

export interface SalesDocumentPayload {
    kind: SalesDocumentKind;
    heading: string;
    documentNumber: string;
    documentDate: string;
    customerName?: string | null;
    customerPhone?: string | null;
    customerAddress?: string | null;
    notes?: string | null;
    items: SalesDocumentItem[];
}

interface BrandDetails {
    name: string;
    address: string;
    phone: string;
    logoDataUrl?: string;
    taxRate: number;
}

function companyName(): string {
    return 'Kermen Aluminum';
}

function companyAddress(): string {
    return 'Piassa Atikelet Tera';
}

function companyPhone(): string {
    return '+25173840930';
}

function companyLogo(): string | undefined {
    return 'K';
}

function salesTaxRate(): number {
    const value = Number(15);

    return Number.isFinite(value) && value >= 0 ? value : 0;
}

function formatCurrency(value: number): string {
    return value.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function formatDate(value: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function initials(value: string): string {
    return value
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

function drawBrandMark(doc: jsPDF, brand: BrandDetails): void {
    const logo = brand.logoDataUrl;

    if (logo) {
        try {
            doc.addImage(logo, 'PNG', 14, 14, 24, 24);
            return;
        } catch {
            // Fall through to the vector badge if the configured image is not usable.
        }
    }

    doc.setFillColor(15, 23, 42);
    doc.roundedRect(14, 14, 24, 24, 4, 4, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(12);
    doc.text(initials(brand.name) || 'SM', 26, 28, { align: 'center' });
}

function buildBrandDetails(): BrandDetails {
    return {
        name: companyName(),
        address: companyAddress(),
        phone: companyPhone(),
        logoDataUrl: companyLogo(),
        taxRate: salesTaxRate(),
    };
}

function buildFileName(kind: SalesDocumentKind, documentNumber: string): string {
    const safeNumber = documentNumber.replace(/[^a-zA-Z0-9._-]+/g, '-');

    return `${kind.toLowerCase()}-${safeNumber || 'document'}.pdf`;
}

export function downloadSalesDocumentPdf(payload: SalesDocumentPayload): void {
    const brand = buildBrandDetails();
    const doc = new jsPDF({ unit: 'mm', format: 'a4' });
    const pageWidth = doc.internal.pageSize.getWidth();
    const leftMargin = 14;
    const contentWidth = pageWidth - leftMargin * 2;
    const rightEdge = pageWidth - leftMargin;

    const subtotal = payload.items.reduce((sum, item) => sum + item.totalPrice, 0);
    const taxAmount = brand.taxRate > 0 ? subtotal * (brand.taxRate / 100) : 0;
    const grandTotal = subtotal + taxAmount;

    doc.setFont('helvetica', 'normal');
    doc.setTextColor(15, 23, 42);

    drawBrandMark(doc, brand);

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(20);
    doc.text('K', 42, 20);

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10);
    doc.setTextColor(15, 23, 42);
    doc.text(brand.address, 42, 26);
    doc.text(brand.phone, 42, 31);

    doc.setDrawColor(226, 232, 240);
    doc.line(leftMargin, 42, rightEdge, 42);

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(22);
    doc.text(payload.kind, rightEdge, 20, { align: 'right' });

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10);
    doc.text(payload.heading, rightEdge, 26, { align: 'right' });
    doc.text(`Document No: ${payload.documentNumber}`, rightEdge, 31, { align: 'right' });
    doc.text(`Date: ${formatDate(payload.documentDate)}`, rightEdge, 36, { align: 'right' });

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(11);

    doc.text('Bill To', leftMargin, 51);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10);

    const customerLines = [payload.customerName, payload.customerPhone, payload.customerAddress].filter((line): line is string =>
        Boolean(line && line.trim() !== ''),
    );

    if (customerLines.length === 0) {
        customerLines.push('Walk-in customer');
    }

    customerLines.forEach((line, index) => {
        doc.text(line, leftMargin, 57 + index * 5);
    });

    if (payload.notes && payload.notes.trim() !== '') {
        doc.setFont('helvetica', 'bold');
        doc.text('Notes', leftMargin, 74);
        doc.setFont('helvetica', 'normal');
        doc.text(payload.notes, leftMargin, 80);
    }

    autoTable(doc, {
        startY: payload.notes && payload.notes.trim() !== '' ? 86 : 74,
        margin: { left: leftMargin, right: leftMargin },
        head: [['Item', 'Qty', 'Unit Price', 'Total']],
        body: payload.items.map((item) => [
            item.name,
            item.quantity.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 4 }),
            formatCurrency(item.unitPrice),
            formatCurrency(item.totalPrice),
        ]),
        theme: 'grid',
        styles: {
            font: 'helvetica',
            fontSize: 9,
            cellPadding: 3,
            textColor: [15, 23, 42],
            lineColor: [226, 232, 240],
            lineWidth: 0.1,
        },
        headStyles: {
            fillColor: [15, 23, 42],
            textColor: [255, 255, 255],
            fontStyle: 'bold',
        },
        columnStyles: {
            1: { halign: 'right' },
            2: { halign: 'right' },
            3: { halign: 'right' },
        },
    });

    const finalY = (doc as jsPDF & { lastAutoTable?: { finalY?: number } }).lastAutoTable?.finalY ?? 100;
    const summaryTop = finalY + 8;
    const summaryBoxWidth = 72;
    const summaryLeft = rightEdge - summaryBoxWidth;

    doc.setFillColor(248, 250, 252);
    doc.setTextColor(15, 23, 42);
    doc.setDrawColor(203, 213, 225);
    doc.roundedRect(summaryLeft, summaryTop, summaryBoxWidth, 30, 3, 3, 'FD');

    doc.setFontSize(10);
    doc.setFont('helvetica', 'normal');
    doc.text('Subtotal', summaryLeft + 5, summaryTop + 8);
    doc.text('Tax', summaryLeft + 5, summaryTop + 14);
    doc.setFont('helvetica', 'bold');
    doc.text('Grand Total', summaryLeft + 5, summaryTop + 22);

    doc.setFont('helvetica', 'normal');
    doc.text(formatCurrency(subtotal), summaryLeft + summaryBoxWidth - 5, summaryTop + 8, { align: 'right' });
    doc.text(
        `${formatCurrency(taxAmount)}${brand.taxRate > 0 ? ` (${brand.taxRate.toFixed(2)}%)` : ''}`,
        summaryLeft + summaryBoxWidth - 5,
        summaryTop + 14,
        {
            align: 'right',
        },
    );
    doc.setFont('helvetica', 'bold');
    doc.text(formatCurrency(grandTotal), summaryLeft + summaryBoxWidth - 5, summaryTop + 22, { align: 'right' });

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(9);
    doc.setTextColor(100, 116, 139);
    doc.text('Thank you for your business.', leftMargin, summaryTop + 38);

    doc.save(buildFileName(payload.kind, payload.documentNumber));
}
