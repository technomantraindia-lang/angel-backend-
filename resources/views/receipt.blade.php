<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - {{ $order->order_number }}</title>
    <link rel="icon" type="image/png" href="{{ asset('logo.png') }}">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #1e293b;
            background: #ffffff;
            margin: 0;
            padding: 40px;
            font-size: 14px;
            line-height: 1.5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 24px;
            margin-bottom: 24px;
        }
        .brand h1 {
            margin: 0;
            color: #0f172a;
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .brand p {
            margin: 4px 0 0 0;
            color: #64748b;
            font-size: 13px;
        }
        .invoice-title {
            text-align: right;
        }
        .invoice-title h2 {
            margin: 0;
            color: #1e40af;
            font-size: 28px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .invoice-title p {
            margin: 6px 0 0 0;
            font-size: 14px;
            color: #334155;
            font-weight: 600;
        }
        .meta-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 32px;
        }
        .meta-card h3 {
            margin: 0 0 8px 0;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 6px;
        }
        .meta-card p {
            margin: 4px 0;
            color: #0f172a;
        }
        .meta-card strong {
            color: #0f172a;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 32px;
        }
        th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.05em;
            text-align: left;
            padding: 12px 16px;
            border-bottom: 2px solid #cbd5e1;
        }
        td {
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
            vertical-align: top;
        }
        tr:last-child td {
            border-bottom: 2px solid #cbd5e1;
        }
        .item-name {
            font-weight: 600;
            color: #0f172a;
        }
        .item-meta {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }
        .right {
            text-align: right;
        }
        .summary-section {
            display: flex;
            justify-content: flex-end;
            margin-top: 24px;
        }
        .summary-table {
            width: 320px;
            margin-bottom: 0;
        }
        .summary-table td {
            padding: 8px 16px;
            border: none;
        }
        .summary-table tr.total-row td {
            border-top: 2px solid #e2e8f0;
            border-bottom: 2px double #cbd5e1;
            padding: 14px 16px;
            font-weight: 800;
            font-size: 18px;
            color: #1e40af;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            background: #dcfce7;
            color: #15803d;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            margin-top: 10px;
        }
        .action-bar {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f1f5f9;
            padding: 12px 24px;
            border-radius: 8px;
        }
        .btn {
            background: #1e40af;
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 13px;
        }
        .btn:hover {
            background: #1d4ed8;
        }
        .btn.secondary {
            background: #64748b;
        }
        .btn.secondary:hover {
            background: #475569;
        }
        @media print {
            .action-bar {
                display: none;
            }
            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="action-bar">
            <span style="font-weight: 600; color: #475569;">📄 Print Preview mode</span>
            <div>
                <button class="btn secondary" onclick="window.close()">Close Window</button>
                <button class="btn" onclick="window.print()">Print Receipt</button>
            </div>
        </div>

        <div class="header">
            <div class="brand">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 12px;">
                    <img src="{{ asset('logo.png') }}" alt="Angel Enterprise Logo" style="height: 64px; object-fit: contain;" />
                    <div>
                        <h1 style="margin: 0; color: #0f172a; font-size: 26px; font-weight: 800; letter-spacing: -0.02em;">Angel Enterprise</h1>
                        <p style="margin: 4px 0 0 0; color: #64748b; font-size: 13px;">Premium B2B Printing & Leaflet Services</p>
                    </div>
                </div>
                <p style="margin: 4px 0; font-size: 12px; color: #475569; line-height: 1.4;">
                    Floor No.: First Floor, Building No./Flat No.: F/4<br>
                    Name Of Premises/Building: Shyamal Complex, Road/Street: New CG Road<br>
                    Nearby Landmark: Kotak Mahindra Bank, Locality/Sub Locality: Chandkheda<br>
                    City/Town/Village: Ahmedabad, District: Ahmedabad, State: Gujarat<br>
                    PIN Code: 382424
                </p>
                <p style="margin: 6px 0; font-size: 12px; color: #475569; line-height: 1.4;">
                    <strong>OFFICE:</strong> 8200391418<br>
                    <strong>CUSTMER CARE WHTAS APP ONLY:</strong> 9724503723
                </p>
                <p style="margin: 4px 0 0 0;">Email: billing@angelprintshop.com | Web: angelprintshop.com</p>
            </div>
            <div class="invoice-title">
                <h2>Receipt</h2>
                <p>No: {{ $order->order_number }}</p>
                <span class="badge">Paid via Wallet</span>
            </div>
        </div>

        <div class="meta-section">
            <div class="meta-card">
                <h3>Billed To (Dealer)</h3>
                <p><strong>{{ $order->dealer->company_name }}</strong></p>
                <p>Contact Person: {{ $order->dealer->name }}</p>
                <p>Phone: {{ $order->dealer->phone }}</p>
                <p>Address: {{ $order->dealer->address }}</p>
            </div>
            <div class="meta-card">
                <h3>Order Details</h3>
                <p>Date Ordered: <strong>{{ $order->created_at->format('d M Y, h:i A') }}</strong></p>
                <p>Date Completed: <strong>{{ $order->completed_at ? $order->completed_at->format('d M Y, h:i A') : 'N/A' }}</strong></p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Product Name/Gsm & Description</th>
                    <th class="right">Print Copies</th>
                    <th class="right">Packs (Sets)</th>
                    <th class="right">Unit Price</th>
                    <th class="right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>
                            <div class="item-name">{{ $item->product_name }}</div>
                            <div class="item-meta">Category: {{ $item->category }}</div>
                        </td>
                        <td class="right">{{ number_format($item->print_copy) }}</td>
                        <td class="right">{{ $item->packs }}</td>
                        <td class="right">₹{{ number_format($item->unit_price, 2) }}</td>
                        <td class="right" style="font-weight: 600;">₹{{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if($order->extraCharges->count() > 0)
            <div style="margin-top: -15px;">
                <h3 style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin-bottom: 12px;">Price Adjustments & Extra Work</h3>
                <table style="margin-bottom: 24px;">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Adjustment Type</th>
                            <th class="right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->extraCharges as $charge)
                            <tr>
                                <td>{{ $charge->description }}</td>
                                <td>
                                    @if($charge->amount < 0)
                                        <span style="color: #16a34a; font-weight: 600;">Price Cut / Wallet Refund</span>
                                    @else
                                        <span style="color: #dc2626; font-weight: 600;">Extra Charge / Service Debit</span>
                                    @endif
                                </td>
                                <td class="right" style="font-weight: 600; color: {{ $charge->amount < 0 ? '#16a34a' : '#dc2626' }}">
                                    {{ $charge->amount < 0 ? '-' : '+' }}₹{{ number_format(abs($charge->amount), 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="summary-section">
            <table class="summary-table">
                <tr>
                    <td style="color: #64748b;">Subtotal</td>
                    <td class="right">₹{{ number_format($order->subtotal, 2) }}</td>
                </tr>
                @if($order->extra_total != 0)
                    <tr>
                        <td style="color: #64748b;">Adjustments Total</td>
                        <td class="right" style="color: {{ $order->extra_total < 0 ? '#16a34a' : '#dc2626' }}">
                            {{ $order->extra_total < 0 ? '-' : '+' }}₹{{ number_format(abs($order->extra_total), 2) }}
                        </td>
                    </tr>
                @endif
                <tr class="total-row">
                    <td>
                        Total Paid
                        <div style="font-size: 11px; font-weight: normal; color: #64748b; margin-top: 4px;">Including 18% GST</div>
                    </td>
                    <td class="right">₹{{ number_format($order->grand_total, 2) }}</td>
                </tr>
            </table>
        </div>

        <div style="margin-top: 60px; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 20px; color: #64748b; font-size: 12px;">
            <p>Thank you for your business! For any query regarding this invoice, write to support@angelprintshop.com</p>
            <p>This is a computer-generated receipt, no signature required.</p>
        </div>
    </div>
</body>
</html>
