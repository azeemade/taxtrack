<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ ucwords($previewables['entity']) }} {{ ucwords($previewables['model']) }}</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <div class="quote-container">
        <table>
            <tr>
                <td>
                    <h2 class="mb-8 h-text">{{ ucwords($previewables['entity']) }} {{ ucwords($previewables['model']) }}
                    </h2>
                    <p>ID: {{ $previewables['id'] }}</p>
                </td>
                <td>
                    <p class="mb-8">{{ ucwords($previewables['entity']) }} ID:
                        <span>{{ $previewables['entity_data']['id'] }}</span>
                    </p>
                    <p class="mb-8">Additional Reference: <span>{{ $previewables['additional_reference'] }}</span></p>
                    <p class="mb-8">Issued Date: <span>{{ $previewables['issued_date'] }}</span></p>
                    <p class="due-date">Due Date: <b>{{ $previewables['due_date'] }}</b></p>
                </td>
            </tr>
        </table>
    </div>

    <div class="quote-parties">
        <table class="parties-table">
            <tr>
                <td class="from-party">
                    <div class="badge">TaxTrack</div>
                    <p class="party-label">{{ ucwords($previewables['model']) }} From:</p>
                    <p class="party-name company">{{ $previewables['company']['name'] }}</p>
                    <p style="width: min-content">{{ $previewables['company']['address'] }}</p>
                </td>
                <td class="to-party">
                    <div class="badge entity">{{ ucwords($previewables['entity']) }}</div>
                    <p class="party-label">{{ ucwords($previewables['model']) }} To:</p>
                    <p class="party-name company">{{ $previewables['entity_data']['name'] }}</p>
                    <p style="width: min-content">{{ $previewables['entity_data']['address'] }}</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="quote-items">
        <div class="quote-wrapper">
            <h3>{{ ucwords($previewables['model']) }} Items</h3>
            <p>Below are the specific item(s) in this {{ $previewables['model'] }} as issued</p>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($previewables['line_items'] as $item)
                    <tr>
                        <td>{{ $item['item_details'] }}</td>
                        <td>{{ $item['quantity'] }}</td>
                        <td>{{ $item['price'] }}</td>
                        <td>{{ $item['amount'] }}</td>
                    </tr>
                @empty
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="quote-summary">
        <table class="summary-table">
            <tr class="sub-total">
                <td>Sub Total</td>
                <td>
                    <div class="amount">
                        £8000
                    </div>
                </td>
            </tr>
            <tr class="addtional-charges">
                <td>Additional charges</td>
                <td style="text-align: end;">
                    @forelse ($previewables['additional_charges'] as $key => $item)
                        <div>
                            <span>
                                <b>{{ $key }}:</b>
                            </span>
                            <span>
                                {{ $item }}
                            </span>
                        </div>
                    @empty
                        <div>0.00</div>
                    @endforelse
                </td>
            </tr>
            <tr class="total">
                <td>Total</td>
                <td>
                    <div class="amount">
                        {{ $previewables['total'] }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="terms">
        <p>Terms and Conditions</p>
        <textarea placeholder="Enter your T&C here"></textarea>
    </div>
    </div>
</body>

</html>
<style>
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        font-family: Arial, sans-serif;
        background-color: white;
        color: #667085;
    }

    .h-text {
        color: #344054;
    }

    .mb-8 {
        margin-bottom: 8px;
    }

    .quote-container {
        padding: 30px;
    }

    .quote-header {
        text-align: center;
        margin-bottom: 20px;
    }

    .quote-header h2 {
        color: #555;
        font-size: 24px;
    }

    .quote-header p {
        color: #777;
        font-size: 14px;
        margin: 5px 0;
    }

    .quote-info table {
        width: 100%;
        margin-top: 10px;
    }

    .quote-info td {
        padding: 5px;
        vertical-align: top;
    }

    .due-date {
        color: #8C1823;
    }

    .quote-parties {
        padding: 0px 30px 30px 30px;
    }

    .parties-table {
        width: 100%;
    }

    .from-party,
    .to-party {
        vertical-align: top;
        padding: 10px;
    }

    .badge {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 3px;
        font-size: 14px;
        background: rgba(21, 112, 239, 0.2);
        color: rgba(21, 112, 239, 1);
    }

    .badge.entity {
        background: rgba(105, 56, 239, 0.2);
        color: rgba(105, 56, 239, 1);
    }

    .party-label {
        font-weight: bold;
        margin: 10px 0 5px;
        font-size: 14px;
    }

    .party-name {
        font-size: 16px;
        font-weight: bold;
        color: white;
    }

    .party-name.company {
        background: rgba(21, 112, 239, 1);
        padding: 10px;
        width: fit-content;
        margin-bottom: 14px;
    }

    .party-name.entity {
        background: rgba(105, 56, 239, 1);
        padding: 10px;
        width: fit-content;
        margin-bottom: 14px;
    }

    .quote-items {
        margin: 0px 30px 30px 30px;
        border: 1px solid #d3d3d3;
        border-radius: 8px;
    }

    .quote-wrapper {
        padding: 20px 24px 20px 24px;
    }

    .quote-items h3 {
        font-size: 18px;
        margin-bottom: 5px;
    }

    .quote-items p {
        font-size: 14px;
        color: #777;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }

    thead {
        background-color: #f0f0f0;
    }

    thead th {
        padding: 10px;
        font-size: 14px;
        text-align: left;
    }

    tbody td {
        padding: 10px;
        border-bottom: 1px solid #f0f0f0;
    }

    .quote-summary {
        background: rgba(249, 250, 251, 1);
        padding: 20px;
        margin: 0px 30px 0px 30px;
        border: 1px solid rgba(234, 236, 240, 1);
        border-radius: 8px;
    }

    .summary-table {
        width: 100%;
        margin-top: 20px;
    }

    .summary-table td div.amount {
        text-align: end;
        border: 1px solid rgba(234, 236, 240, 1);
        border-radius: 8px;
        padding: 15px 15px 15px 0;
    }

    .total {
        background: rgba(234, 236, 240, 1);
    }

    .total td {
        font-weight: bold;
        font-size: 18px;
    }

    .total td div {
        background: white;
    }

    .terms {
        margin: 80px 30px 0px 30px;
        padding: 32px 32px 32px 32px;
        background: rgba(249, 250, 251, 1);
        border: 1px solid rgba(234, 236, 240, 1);
        border-radius: 8px;
    }

    .terms p {
        margin-bottom: 10px;
        font-size: 14px;
    }

    textarea {
        width: 100%;
        height: 100px;
        border: 1px solid #ccc;
        border-radius: 5px;
        padding: 10px;
        font-size: 14px;
    }
</style>
