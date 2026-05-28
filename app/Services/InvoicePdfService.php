<?php
namespace App\Services;

class InvoicePdfService
{
    public function generate(
        array  $book,
        array  $invoice,
        array  $items,
        ?array $customer,
        ?array $supplier,
        ?array $details,
        ?array $creator
    ): void {
        if (!class_exists('\Mpdf\Mpdf')) {
            // Fallback: redirect to browser-printable thermal view
            $bookId    = $book['id'];
            $invoiceId = $invoice['id'];
            header("Location: /books/{$bookId}/invoices/{$invoiceId}/thermal?w=80&autoprint=1");
            exit;
        }

        // ── Settings ──────────────────────────────────────────────────────────
        $theme      = $invoice['theme_color']     ?? $book['theme_color']   ?? '#000000';
        $font       = $details['invoice_font']    ?? 'DejaVu Sans';
        $sym        = $invoice['currency_symbol'] ?? '৳';
        $curCode    = $invoice['currency_code']   ?? 'BDT';
        $timezone   = $book['timezone']           ?? 'Asia/Dhaka';

        // Fix timezone for date display
        $createdAt = new \DateTime($invoice['created_at'], new \DateTimeZone('UTC'));
        $createdAt->setTimezone(new \DateTimeZone($timezone));
        $createdAtStr = $createdAt->format('F jS, Y \a\t h:i A');

        // ── Parties ───────────────────────────────────────────────────────────
        $businessName  = $details['business_name'] ?? $book['name'];
        $businessPhone = $book['phone']  ?? $details['phone']  ?? '';
        $businessEmail = $book['email']  ?? $details['email']  ?? '';
        $businessAddr  = str_replace("\n", ', ', trim($book['address'] ?? $details['address'] ?? ''));

        $party      = $customer ?? $supplier ?? null;
        $partyName  = $party['name']    ?? 'Walk-in Customer';
        $partyAddr  = str_replace("\n", ', ', trim($party['address'] ?? ''));
        $partyPhone = $party['phone']   ?? '';
        $partyEmail = $party['email']   ?? '';

        $creatorName  = $creator['name'] ?? 'Staff';
        $invoiceNo    = $invoice['invoice_no'];
        $invoiceDate  = date('d|m|Y', strtotime($invoice['date']));
        $dueDate      = $invoice['due_date'] ? date('d|m|Y', strtotime($invoice['due_date'])) : '';

        $noteCustomer = $invoice['note_customer'] ?? $invoice['notes'] ?? '';
        $noteSeller   = $invoice['note_seller']   ?? '';

        // ── Logo ──────────────────────────────────────────────────────────────
        $logoHtml = '<span style="font-size:24px;font-style:italic;color:#ccc;font-family:serif">Logo</span>';
        if (!empty($book['logo'])) {
            $logoPath = config('upload.path') . '/' . $book['logo'];
            if (file_exists($logoPath)) {
                $logoHtml = '<img src="'.htmlspecialchars($logoPath).'" style="max-height:65px;max-width:170px">';
            }
        }

        // ── QR ────────────────────────────────────────────────────────────────
        $token      = $invoice['public_token'] ?? '';
        $invoiceUrl = config('url') . '/invoice/' . $token;
        $qrUrl      = 'https://api.qrserver.com/v1/create-qr-code/?size=85x85&data=' . urlencode($invoiceUrl);

        // ── Totals ────────────────────────────────────────────────────────────
        $subtotal = (float)$invoice['subtotal'];
        $discount = (float)$invoice['discount'];
        $points   = (float)($invoice['points_discount'] ?? 0);
        $delivery = (float)($invoice['delivery_charge'] ?? 0);
        $rounding = (float)($invoice['rounding']        ?? 0);
        $tax      = (float)$invoice['tax'];
        $total    = (float)$invoice['total'];
        $deliveryMethod = $invoice['delivery_method'] ?? '';
        $paymentMethod  = $invoice['payment_method']  ?? '';

        // Amount in words
        $inWords = $this->numberToWords((int)round($total), $curCode) . ' Only';

        // ── Item rows ─────────────────────────────────────────────────────────
        $itemRowsHtml = '';
        foreach ($items as $n => $item) {
            $qty     = rtrim(rtrim(number_format((float)$item['qty'],3,'.',''),'0'),'.');
            $variant = $this->e($item['variant'] ?? '');
            $sku     = $this->e($item['sku'] ?? '');
            $bg      = ($n % 2 === 0) ? '#ffffff' : '#f9f9f9';
            $itemRowsHtml .= '
            <tr style="background:'.$bg.'">
                <td style="border:1px solid #ddd;padding:7px 8px;text-align:center;font-size:12px">'.($n+1).'</td>
                <td style="border:1px solid #ddd;padding:7px 8px;font-size:13px">'.$this->e($item['description']).'</td>
                <td style="border:1px solid #ddd;padding:7px 8px;text-align:center;font-size:12px">'.$variant.'</td>
                <td style="border:1px solid #ddd;padding:7px 8px;text-align:center;font-size:12px">'.$sku.'</td>
                <td style="border:1px solid #ddd;padding:7px 8px;text-align:center;font-size:12px">'.$qty.'</td>
                <td style="border:1px solid #ddd;padding:7px 8px;text-align:right;font-size:12px">'.$sym.number_format((float)$item['unit_price'],0).'</td>
                <td style="border:1px solid #ddd;padding:7px 8px;text-align:right;font-size:12px;font-weight:600">'.$sym.number_format((float)$item['line_total'],0).'</td>
            </tr>';
        }

        // ── Totals rows ───────────────────────────────────────────────────────
        $dash      = '<span style="display:inline-block;width:55px;border-bottom:1px solid #aaa;vertical-align:middle;margin:0 4px"></span>';
        $discLabel = $discount > 0
            ? '['.round($discount/($subtotal?:1)*100).'%]('.$sym.number_format($discount,0).')'
            : '-------';

        $totalsRows = '
        <tr><td style="padding:4px 0 4px 6px;font-size:11px;font-weight:700;letter-spacing:.5px">SUBTOTAL</td>
            <td style="padding:4px 2px;text-align:center">'.$dash.'</td>
            <td style="padding:4px 6px 4px 0;text-align:right;font-size:11px">'.$sym.number_format($subtotal,0).'</td></tr>
        <tr><td style="padding:4px 0 4px 6px;font-size:11px;font-weight:700;letter-spacing:.5px">DISCOUNT</td>
            <td style="padding:4px 2px;text-align:center">'.$dash.'</td>
            <td style="padding:4px 6px 4px 0;text-align:right;font-size:11px">'.$discLabel.'</td></tr>
        <tr><td style="padding:4px 0 4px 6px;font-size:11px;font-weight:700;letter-spacing:.5px">POINTS</td>
            <td style="padding:4px 2px;text-align:center">'.$dash.'</td>
            <td style="padding:4px 6px 4px 0;text-align:right;font-size:11px">'.($points>0 ? '('.$sym.number_format($points,0).')' : '-------').'</td></tr>
        <tr><td style="padding:4px 0 4px 6px;font-size:11px;font-weight:700;letter-spacing:.5px">DELIVERY</td>
            <td style="padding:4px 2px;text-align:center">'.$dash.'</td>
            <td style="padding:4px 6px 4px 0;text-align:right;font-size:11px">'.($delivery>0 ? $sym.number_format($delivery,0) : '-------').'</td></tr>';
        if ($rounding > 0) {
            $totalsRows .= '
        <tr><td style="padding:4px 0 4px 6px;font-size:11px;font-weight:700;letter-spacing:.5px">ROUNDING</td>
            <td style="padding:4px 2px;text-align:center">'.$dash.'</td>
            <td style="padding:4px 6px 4px 0;text-align:right;font-size:11px">(-'.$sym.number_format($rounding,0).')</td></tr>';
        }
        $totalsRows .= '
        <tr style="border-top:2px solid '.$theme.'">
            <td style="padding:7px 0 4px 6px;font-size:12px;font-weight:800;color:'.$theme.'">GRAND TOTAL</td>
            <td style="padding:7px 2px 4px;text-align:center">'.$dash.'</td>
            <td style="padding:7px 6px 4px 0;text-align:right;font-size:13px;font-weight:800;color:'.$theme.'">'.$sym.number_format($total,0).'</td>
        </tr>';

        // ── HTML ──────────────────────────────────────────────────────────────
        $html = '<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:"'.$font.'",sans-serif; font-size:13px; color:#1a1a1a; }
table { border-collapse:collapse; }
</style>
</head><body>

<!-- HEADER: Invoice+QR left | Logo right -->
<table style="width:100%;margin-bottom:8px">
<tr>
    <td style="width:60%;vertical-align:top">
        <table><tr>
            <td style="padding-right:14px;vertical-align:middle">
                <span style="font-size:42px;font-weight:900;letter-spacing:-2px">Invoice</span>
            </td>
            <td style="vertical-align:middle">
                <img src="'.$qrUrl.'" style="width:78px;height:78px">
            </td>
        </tr></table>
    </td>
    <td style="width:40%;vertical-align:top;text-align:right">'.$logoHtml.'</td>
</tr>
</table>

<!-- INVOICE NO + DATE -->
<div style="border-top:2.5px solid #111;border-bottom:2.5px solid #111;padding:7px 2px;margin-bottom:0">
<table style="width:100%"><tr>
    <td style="font-size:13px;font-weight:700"><b>Invoice No:</b> '.$this->e($invoiceNo).'</td>
    <td style="text-align:right;font-size:13px;font-weight:700"><b>Date:</b> '.$invoiceDate.($dueDate ? '&nbsp;&nbsp;<b>Due:</b> '.$dueDate : '').'</td>
</tr></table>
</div>

<!-- BILL TO | BILL FROM -->
<table style="width:100%;border-bottom:1px solid #ccc">
<tr>
    <td style="width:50%;vertical-align:top;padding:10px 10px 10px 2px;border-right:1px solid #ccc">
        <div style="font-size:10px;font-weight:800;letter-spacing:.5px;margin-bottom:6px">BILL TO -</div>
        '.($party ? '
        <div style="line-height:1.8;font-size:13px">
            '.$this->e($partyName).'<br>
            '.($partyAddr  ? $this->e($partyAddr).'<br>' : '').'
            '.($partyPhone ? $this->e($partyPhone).'<br>': '').'
            '.($partyEmail ? $this->e($partyEmail)       : '').'
        </div>' : '<span style="color:#888;font-size:13px">Walk-in Customer</span>').'
        <div style="margin-top:10px;font-size:13px"><b>Note:</b> '.$this->e($noteCustomer).'</div>
    </td>
    <td style="width:50%;vertical-align:top;padding:10px 2px 10px 14px">
        <div style="font-size:10px;font-weight:800;letter-spacing:.5px;margin-bottom:6px">BILL FROM -</div>
        <div style="line-height:1.8;font-size:13px">
            '.$this->e($businessName).'<br>
            '.($businessAddr  ? $this->e($businessAddr).'<br>'  : '').'
            '.($businessPhone ? $this->e($businessPhone).'<br>' : '').'
            '.($businessEmail ? $this->e($businessEmail)         : '').'
        </div>
        <div style="margin-top:10px;font-size:13px"><b>Note:</b> '.$this->e($noteSeller).'</div>
    </td>
</tr>
</table>

<!-- ITEMS TABLE -->
<table style="width:100%">
<thead>
    <tr style="background:'.$theme.';color:#fff">
        <th style="padding:9px 8px;font-size:10px;font-weight:800;letter-spacing:.5px;text-align:center;width:30px;border:1px solid '.$theme.'">NO</th>
        <th style="padding:9px 8px;font-size:10px;font-weight:800;letter-spacing:.5px;text-align:center;border:1px solid '.$theme.'">DESCRIPTION</th>
        <th style="padding:9px 8px;font-size:10px;font-weight:800;letter-spacing:.5px;text-align:center;width:88px;border:1px solid '.$theme.'">COLOR/SIZE</th>
        <th style="padding:9px 8px;font-size:10px;font-weight:800;letter-spacing:.5px;text-align:center;width:55px;border:1px solid '.$theme.'">ID</th>
        <th style="padding:9px 8px;font-size:10px;font-weight:800;letter-spacing:.5px;text-align:center;width:42px;border:1px solid '.$theme.'">QTY</th>
        <th style="padding:9px 8px;font-size:10px;font-weight:800;letter-spacing:.5px;text-align:center;width:78px;border:1px solid '.$theme.'">UNIT PRICE</th>
        <th style="padding:9px 8px;font-size:10px;font-weight:800;letter-spacing:.5px;text-align:center;width:72px;border:1px solid '.$theme.'">TOTAL</th>
    </tr>
</thead>
<tbody>'.$itemRowsHtml.'</tbody>
</table>

<!-- BELOW TABLE: Delivery left | Totals right -->
<table style="width:100%;margin-top:10px">
<tr>
    <td style="width:50%;vertical-align:top;font-size:12px;padding-top:2px">
        '.($deliveryMethod ? '<div style="margin-bottom:4px"><b>Delivery Method:</b>&nbsp;&nbsp;'.$this->e($deliveryMethod).'</div>' : '').'
        '.($paymentMethod  ? '<div><b>Payment Method:</b>&nbsp;&nbsp;'.$this->e($paymentMethod).'</div>'  : '').'
    </td>
    <td style="width:50%;vertical-align:top">
        <table style="width:100%">'.$totalsRows.'</table>
    </td>
</tr>
</table>

<!-- AMOUNT IN WORDS -->
<div style="border:1px solid #ddd;border-radius:4px;padding:8px 12px;margin-top:10px;font-size:12px;color:#444;background:#fafafa">
    <b>In Words:</b> '.$this->e($inWords).'
</div>

<!-- SIGNATURES fixed to bottom -->
<div style="position:absolute;bottom:28mm;left:14mm;right:14mm">
    <table style="width:100%">
    <tr>
        <td style="width:35%;border-top:1.5px solid #111;padding-top:6px;font-size:12px;font-weight:700">
            Buyer ('.$this->e($partyName).')
        </td>
        <td style="width:30%"></td>
        <td style="width:35%;border-top:1.5px solid #111;padding-top:6px;font-size:12px;font-weight:700;text-align:right">
            Seller ('.$this->e($creatorName).')
        </td>
    </tr>
    </table>
    <div style="text-align:center;font-style:italic;color:'.$theme.';font-size:12px;margin-top:14px;border-top:2px solid '.$theme.';padding-top:8px">
        It was a pleasure doing business with you, we hope to hear from you soon!
    </div>
    <div style="text-align:center;font-size:10px;color:#888;border-top:1.5px solid #111;padding-top:6px;margin-top:6px">
        This invoice was generated using Byabsayee (https://byabsayee.com)<br>
        by '.$this->e($creatorName).' on '.$createdAtStr.'
    </div>
</div>

</body></html>';

        try {
        $mpdf = new \Mpdf\Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_top'    => 12,
            'margin_bottom' => 48,
            'margin_left'   => 14,
            'margin_right'  => 14,
            'tempDir'       => sys_get_temp_dir(),
            'default_font'  => $font,
        ]);
        $mpdf->SetTitle('Invoice ' . $invoiceNo);
        $mpdf->WriteHTML($html);
        $mpdf->Output('Invoice-' . $invoiceNo . '.pdf', 'D');
        } catch (\Throwable $e) {
            error_log('mPDF error: ' . $e->getMessage());
            header('Content-Type: text/plain; charset=utf-8');
            echo 'PDF generation failed: ' . htmlspecialchars($e->getMessage()) . "\n\nTry the thermal print instead.";
        }
    }

    // ── Number to words ───────────────────────────────────────────────────────
    private function numberToWords(int $n, string $curCode = 'BDT'): string
    {
        $ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine',
                 'Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen',
                 'Seventeen','Eighteen','Nineteen'];
        $tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
        $currencies = ['BDT'=>'Taka','USD'=>'Dollar','EUR'=>'Euro','GBP'=>'Pound',
                       'INR'=>'Rupee','SAR'=>'Riyal','AED'=>'Dirham'];
        $currName = $currencies[$curCode] ?? $curCode;

        $conv = function(int $n) use ($ones, $tens, &$conv): string {
            if ($n < 20)       return $ones[$n];
            if ($n < 100)      return $tens[(int)($n/10)].($n%10?' '.$ones[$n%10]:'');
            if ($n < 1000)     return $ones[(int)($n/100)].' Hundred'.($n%100?' '.$conv($n%100):'');
            if ($n < 100000)   return $conv((int)($n/1000)).' Thousand'.($n%1000?' '.$conv($n%1000):'');
            if ($n < 10000000) return $conv((int)($n/100000)).' Lakh'.($n%100000?' '.$conv($n%100000):'');
            return $conv((int)($n/10000000)).' Crore'.($n%10000000?' '.$conv($n%10000000):'');
        };

        if ($n === 0) return 'Zero '.$currName;
        return $conv($n).' '.$currName;
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
