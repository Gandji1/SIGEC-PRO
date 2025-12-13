/**
 * Service d'impression pour SIGEC
 * Génère et imprime tickets de caisse et factures
 */

class PrintService {
  constructor() {
    this.defaultStyles = `
      @page { margin: 0; size: 80mm auto; }
      * { margin: 0; padding: 0; box-sizing: border-box; }
      body { font-family: 'Courier New', monospace; font-size: 12px; padding: 5mm; }
      .receipt { width: 70mm; margin: 0 auto; }
      .header { text-align: center; border-bottom: 1px dashed #000; padding-bottom: 5px; margin-bottom: 5px; }
      .logo { font-size: 18px; font-weight: bold; }
      .company { font-size: 10px; }
      .items { margin: 10px 0; }
      .item { display: flex; justify-content: space-between; margin: 3px 0; }
      .item-name { flex: 1; }
      .item-qty { width: 30px; text-align: center; }
      .item-price { width: 60px; text-align: right; }
      .separator { border-top: 1px dashed #000; margin: 5px 0; }
      .total { font-weight: bold; font-size: 14px; }
      .footer { text-align: center; font-size: 10px; margin-top: 10px; }
      .qr { text-align: center; margin: 10px 0; }
    `;
  }

  /**
   * Formater un montant
   */
  formatAmount(amount, currency = 'XOF') {
    return new Intl.NumberFormat('fr-FR').format(amount || 0) + ' ' + currency;
  }

  /**
   * Formater une date
   */
  formatDate(date) {
    return new Date(date).toLocaleString('fr-FR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  /**
   * Générer un ticket de caisse
   */
  generateReceipt(order, tenant, options = {}) {
    const { showQR = false, copies = 1, showTax = true, title = 'TICKET DE CAISSE' } = options;
    
    const items = order.items || [];
    const subtotal = order.subtotal || items.reduce((sum, item) => sum + (item.quantity * (item.unit_price || item.product?.selling_price || 0)), 0);
    const tax = showTax ? (order.tax_amount || order.tax || 0) : 0;
    const discount = order.discount_amount || 0;
    const total = order.total || subtotal + tax - discount;

    const html = `
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="UTF-8">
        <title>${title} #${order.reference}</title>
        <style>${this.defaultStyles}</style>
      </head>
      <body>
        <div class="receipt">
          <div class="header">
            <div class="logo">${tenant?.name || 'SIGEC'}</div>
            <div style="font-size: 14px; font-weight: bold; margin: 5px 0;">${title}</div>
            <div class="company">
              ${tenant?.address || ''}<br>
              ${tenant?.phone ? 'Tél: ' + tenant.phone : ''}<br>
              ${tenant?.tax_id ? 'NIF: ' + tenant.tax_id : ''}
            </div>
          </div>

          <div style="margin: 10px 0; font-size: 11px;">
            <div>N°: <strong>#${order.reference}</strong></div>
            <div>Date: ${this.formatDate(order.created_at)}</div>
            ${order.table_number ? `<div>Table: ${order.table_number}</div>` : ''}
            ${order.server_name ? `<div>Serveur: ${order.server_name}</div>` : ''}
          </div>

          <div class="separator"></div>

          <div class="items">
            ${items.map(item => `
              <div class="item">
                <span class="item-name">${item.product?.name || item.name}</span>
                <span class="item-qty">x${item.quantity}</span>
                <span class="item-price">${this.formatAmount(item.quantity * item.unit_price)}</span>
              </div>
            `).join('')}
          </div>

          <div class="separator"></div>

          <div style="margin: 5px 0;">
            <div class="item">
              <span>Sous-total</span>
              <span>${this.formatAmount(subtotal)}</span>
            </div>
            ${tax > 0 ? `
              <div class="item">
                <span>TVA</span>
                <span>${this.formatAmount(tax)}</span>
              </div>
            ` : ''}
            ${discount > 0 ? `
              <div class="item">
                <span>Remise</span>
                <span>-${this.formatAmount(discount)}</span>
              </div>
            ` : ''}
          </div>

          <div class="separator"></div>

          <div class="item total">
            <span>TOTAL</span>
            <span>${this.formatAmount(total)}</span>
          </div>

          ${order.payment_method ? `
            <div style="margin-top: 5px; font-size: 11px;">
              Paiement: ${this.getPaymentMethodLabel(order.payment_method)}
              ${order.received_amount ? `<br>Reçu: ${this.formatAmount(order.received_amount)}` : ''}
              ${order.change_amount ? `<br>Rendu: ${this.formatAmount(order.change_amount)}` : ''}
            </div>
          ` : ''}

          ${showQR ? `
            <div class="qr">
              <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=${encodeURIComponent(order.reference)}" alt="QR" />
            </div>
          ` : ''}

          <div class="footer">
            <div class="separator"></div>
            <p>Merci de votre visite!</p>
            <p style="font-size: 9px;">Powered by SIGEC</p>
          </div>
        </div>
      </body>
      </html>
    `;

    return html;
  }

  /**
   * Générer une facture PDF
   */
  generateInvoice(sale, tenant, customer, options = {}) {
    const items = sale.items || [];
    const subtotal = items.reduce((sum, item) => sum + (item.quantity * item.unit_price), 0);
    const tax = sale.tax_amount || 0;
    const total = sale.total || subtotal + tax;

    const html = `
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="UTF-8">
        <title>Facture ${sale.invoice_number || sale.reference}</title>
        <style>
          @page { margin: 15mm; size: A4; }
          * { margin: 0; padding: 0; box-sizing: border-box; }
          body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
          .invoice { max-width: 800px; margin: 0 auto; }
          .header { display: flex; justify-content: space-between; margin-bottom: 30px; }
          .logo { font-size: 28px; font-weight: bold; color: #FF9F43; }
          .company-info { text-align: right; font-size: 11px; }
          .invoice-title { font-size: 24px; color: #FF9F43; margin-bottom: 20px; }
          .meta { display: flex; justify-content: space-between; margin-bottom: 30px; }
          .meta-box { background: #f8f9fa; padding: 15px; border-radius: 8px; width: 48%; }
          .meta-title { font-weight: bold; margin-bottom: 10px; color: #666; }
          table { width: 100%; border-collapse: collapse; margin: 20px 0; }
          th { background: #FF9F43; color: white; padding: 12px; text-align: left; }
          td { padding: 12px; border-bottom: 1px solid #eee; }
          .text-right { text-align: right; }
          .totals { margin-left: auto; width: 300px; }
          .totals tr td { padding: 8px 12px; }
          .totals .total { font-size: 16px; font-weight: bold; background: #f8f9fa; }
          .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #666; }
          .payment-info { background: #e8f5e9; padding: 15px; border-radius: 8px; margin-top: 20px; }
        </style>
      </head>
      <body>
        <div class="invoice">
          <div class="header">
            <div>
              <div class="logo">${tenant?.name || 'SIGEC'}</div>
              <div style="margin-top: 5px; font-size: 11px;">
                ${tenant?.address || ''}<br>
                ${tenant?.phone ? 'Tél: ' + tenant.phone : ''}<br>
                ${tenant?.email || ''}
              </div>
            </div>
            <div class="company-info">
              ${tenant?.tax_id ? '<strong>NIF:</strong> ' + tenant.tax_id + '<br>' : ''}
              ${tenant?.rccm ? '<strong>RCCM:</strong> ' + tenant.rccm + '<br>' : ''}
            </div>
          </div>

          <div class="invoice-title">FACTURE</div>

          <div class="meta">
            <div class="meta-box">
              <div class="meta-title">FACTURÉ À</div>
              <strong>${customer?.name || 'Client comptoir'}</strong><br>
              ${customer?.address || ''}<br>
              ${customer?.phone ? 'Tél: ' + customer.phone : ''}<br>
              ${customer?.email || ''}
            </div>
            <div class="meta-box">
              <div class="meta-title">DÉTAILS FACTURE</div>
              <strong>N°:</strong> ${sale.invoice_number || sale.reference}<br>
              <strong>Date:</strong> ${this.formatDate(sale.created_at)}<br>
              <strong>Échéance:</strong> ${sale.due_date ? this.formatDate(sale.due_date) : 'Comptant'}
            </div>
          </div>

          <table>
            <thead>
              <tr>
                <th>Description</th>
                <th class="text-right">Qté</th>
                <th class="text-right">Prix Unit.</th>
                <th class="text-right">Total</th>
              </tr>
            </thead>
            <tbody>
              ${items.map(item => `
                <tr>
                  <td>${item.product?.name || item.name}</td>
                  <td class="text-right">${item.quantity}</td>
                  <td class="text-right">${this.formatAmount(item.unit_price)}</td>
                  <td class="text-right">${this.formatAmount(item.quantity * item.unit_price)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>

          <table class="totals">
            <tr>
              <td>Sous-total</td>
              <td class="text-right">${this.formatAmount(subtotal)}</td>
            </tr>
            ${tax > 0 ? `
              <tr>
                <td>TVA (${sale.tax_rate || 18}%)</td>
                <td class="text-right">${this.formatAmount(tax)}</td>
              </tr>
            ` : ''}
            <tr class="total">
              <td><strong>TOTAL</strong></td>
              <td class="text-right"><strong>${this.formatAmount(total)}</strong></td>
            </tr>
          </table>

          ${sale.payment_status === 'paid' ? `
            <div class="payment-info">
              ✓ Facture payée le ${this.formatDate(sale.paid_at || sale.updated_at)}
            </div>
          ` : ''}

          <div class="footer">
            <p>Merci pour votre confiance!</p>
            <p style="margin-top: 10px;">
              ${tenant?.bank_name ? 'Banque: ' + tenant.bank_name + ' | ' : ''}
              ${tenant?.bank_account ? 'Compte: ' + tenant.bank_account : ''}
            </p>
            <p style="margin-top: 20px; font-size: 9px;">Document généré par SIGEC - Système Intégré de Gestion</p>
          </div>
        </div>
      </body>
      </html>
    `;

    return html;
  }

  /**
   * Obtenir le label de la méthode de paiement
   */
  getPaymentMethodLabel(method) {
    const labels = {
      cash: 'Espèces',
      card: 'Carte bancaire',
      mobile_money: 'Mobile Money',
      mtn: 'MTN Money',
      moov: 'Moov Money',
      fedapay: 'FedaPay',
      kakiapay: 'KakiaPay',
      transfer: 'Virement',
      check: 'Chèque',
    };
    return labels[method] || method;
  }

  /**
   * Imprimer un document
   */
  print(html, options = {}) {
    const { silent = false } = options;
    
    const printWindow = window.open('', '_blank', 'width=400,height=600');
    printWindow.document.write(html);
    printWindow.document.close();
    
    printWindow.onload = () => {
      if (silent) {
        printWindow.print();
        printWindow.close();
      } else {
        printWindow.focus();
        printWindow.print();
      }
    };
  }

  /**
   * Télécharger en PDF (via impression)
   */
  downloadPDF(html, filename) {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(html);
    printWindow.document.close();
    printWindow.document.title = filename;
    
    printWindow.onload = () => {
      printWindow.print();
    };
  }

  /**
   * Imprimer un ticket de caisse
   */
  printReceipt(order, tenant, options = {}) {
    const html = this.generateReceipt(order, tenant, options);
    this.print(html, { silent: true });
  }

  /**
   * Imprimer une facture
   */
  printInvoice(sale, tenant, customer, options = {}) {
    const html = this.generateInvoice(sale, tenant, customer, options);
    this.print(html, options);
  }

  /**
   * Télécharger une facture PDF
   */
  downloadInvoice(sale, tenant, customer) {
    const html = this.generateInvoice(sale, tenant, customer);
    this.downloadPDF(html, `Facture_${sale.invoice_number || sale.reference}.pdf`);
  }
}

// Instance singleton
const printService = new PrintService();
export default printService;
