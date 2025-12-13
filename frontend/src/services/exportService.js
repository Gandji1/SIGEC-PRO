/**
 * Service d'export universel - Excel, PDF, Word
 * Utilisable dans toute l'application SIGEC
 */

import * as XLSX from 'xlsx';
import { saveAs } from 'file-saver';
import jsPDF from 'jspdf';
import 'jspdf-autotable';
import { Document, Packer, Paragraph, Table, TableRow, TableCell, TextRun, HeadingLevel, AlignmentType, WidthType, BorderStyle } from 'docx';

// Configuration par défaut
const DEFAULT_CONFIG = {
  companyName: 'SIGEC',
  currency: 'XAF',
  dateFormat: 'fr-FR',
};

/**
 * Formater une valeur pour l'export
 */
const formatValue = (value, type = 'text') => {
  if (value === null || value === undefined) return '';
  
  switch (type) {
    case 'currency':
      return new Intl.NumberFormat('fr-FR', { 
        style: 'currency', 
        currency: DEFAULT_CONFIG.currency,
        maximumFractionDigits: 0 
      }).format(value);
    case 'number':
      return new Intl.NumberFormat('fr-FR').format(value);
    case 'date':
      return new Date(value).toLocaleDateString(DEFAULT_CONFIG.dateFormat);
    case 'datetime':
      return new Date(value).toLocaleString(DEFAULT_CONFIG.dateFormat);
    case 'percentage':
      return `${value}%`;
    default:
      return String(value);
  }
};

/**
 * Préparer les données pour l'export
 */
const prepareData = (data, columns) => {
  return data.map(row => {
    const newRow = {};
    columns.forEach(col => {
      const value = col.accessor ? 
        (typeof col.accessor === 'function' ? col.accessor(row) : row[col.accessor]) 
        : row[col.key];
      newRow[col.header || col.key] = formatValue(value, col.type);
    });
    return newRow;
  });
};

/**
 * Export vers Excel (.xlsx)
 */
export const exportToExcel = (data, columns, filename = 'export', options = {}) => {
  try {
    const { sheetName = 'Données', title, subtitle } = options;
    
    // Préparer les données
    const preparedData = prepareData(data, columns);
    
    // Créer le workbook
    const wb = XLSX.utils.book_new();
    
    // Créer les lignes d'en-tête si titre fourni
    const headerRows = [];
    if (title) {
      headerRows.push([title]);
      headerRows.push([subtitle || '']);
      headerRows.push([`Généré le: ${new Date().toLocaleString('fr-FR')}`]);
      headerRows.push([]);
    }
    
    // Créer la feuille avec les données
    const ws = XLSX.utils.json_to_sheet(preparedData, { origin: headerRows.length });
    
    // Ajouter les en-têtes si présents
    if (headerRows.length > 0) {
      XLSX.utils.sheet_add_aoa(ws, headerRows, { origin: 'A1' });
    }
    
    // Ajuster la largeur des colonnes
    const colWidths = columns.map(col => ({
      wch: Math.max(
        (col.header || col.key || '').length,
        ...preparedData.map(row => String(row[col.header || col.key] || '').length)
      ) + 2
    }));
    ws['!cols'] = colWidths;
    
    // Ajouter la feuille au workbook
    XLSX.utils.book_append_sheet(wb, ws, sheetName);
    
    // Générer et télécharger le fichier
    const excelBuffer = XLSX.write(wb, { bookType: 'xlsx', type: 'array' });
    const blob = new Blob([excelBuffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    saveAs(blob, `${filename}.xlsx`);
    
    return true;
  } catch (error) {
    console.error('Erreur export Excel:', error);
    throw error;
  }
};

/**
 * Export vers PDF
 */
export const exportToPDF = (data, columns, filename = 'export', options = {}) => {
  try {
    const { 
      title = 'Rapport', 
      subtitle = '',
      orientation = 'portrait',
      pageSize = 'a4',
      logo = null,
      footer = true
    } = options;
    
    // Créer le document PDF
    const doc = new jsPDF({
      orientation,
      unit: 'mm',
      format: pageSize
    });
    
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    let yPos = 15;
    
    // En-tête avec titre
    doc.setFontSize(18);
    doc.setFont('helvetica', 'bold');
    doc.setTextColor(2, 132, 199); // brand-600
    doc.text(title, pageWidth / 2, yPos, { align: 'center' });
    yPos += 8;
    
    // Sous-titre
    if (subtitle) {
      doc.setFontSize(11);
      doc.setFont('helvetica', 'normal');
      doc.setTextColor(100);
      doc.text(subtitle, pageWidth / 2, yPos, { align: 'center' });
      yPos += 6;
    }
    
    // Date de génération
    doc.setFontSize(9);
    doc.setTextColor(150);
    doc.text(`Généré le: ${new Date().toLocaleString('fr-FR')}`, pageWidth / 2, yPos, { align: 'center' });
    yPos += 10;
    
    // Préparer les données pour le tableau
    const headers = columns.map(col => col.header || col.key);
    const rows = data.map(row => 
      columns.map(col => {
        const value = col.accessor ? 
          (typeof col.accessor === 'function' ? col.accessor(row) : row[col.accessor]) 
          : row[col.key];
        return formatValue(value, col.type);
      })
    );
    
    // Créer le tableau
    doc.autoTable({
      head: [headers],
      body: rows,
      startY: yPos,
      theme: 'grid',
      headStyles: {
        fillColor: [2, 132, 199],
        textColor: 255,
        fontStyle: 'bold',
        fontSize: 9,
        halign: 'center'
      },
      bodyStyles: {
        fontSize: 8,
        cellPadding: 3
      },
      alternateRowStyles: {
        fillColor: [245, 247, 250]
      },
      columnStyles: columns.reduce((acc, col, idx) => {
        if (col.type === 'currency' || col.type === 'number') {
          acc[idx] = { halign: 'right' };
        }
        return acc;
      }, {}),
      margin: { top: 15, right: 10, bottom: 20, left: 10 },
      didDrawPage: (data) => {
        // Footer sur chaque page
        if (footer) {
          doc.setFontSize(8);
          doc.setTextColor(150);
          doc.text(
            `Page ${data.pageNumber}`,
            pageWidth / 2,
            pageHeight - 10,
            { align: 'center' }
          );
          doc.text(
            DEFAULT_CONFIG.companyName,
            10,
            pageHeight - 10
          );
        }
      }
    });
    
    // Télécharger le fichier
    doc.save(`${filename}.pdf`);
    
    return true;
  } catch (error) {
    console.error('Erreur export PDF:', error);
    throw error;
  }
};

/**
 * Export vers Word (.docx)
 */
export const exportToWord = async (data, columns, filename = 'export', options = {}) => {
  try {
    const { 
      title = 'Rapport', 
      subtitle = '',
      description = ''
    } = options;
    
    // Préparer les lignes du tableau
    const tableRows = [
      // En-tête
      new TableRow({
        tableHeader: true,
        children: columns.map(col => 
          new TableCell({
            children: [new Paragraph({
              children: [new TextRun({ text: col.header || col.key, bold: true, color: 'FFFFFF' })],
              alignment: AlignmentType.CENTER
            })],
            shading: { fill: '0284c7' }
          })
        )
      }),
      // Données
      ...data.map(row => 
        new TableRow({
          children: columns.map(col => {
            const value = col.accessor ? 
              (typeof col.accessor === 'function' ? col.accessor(row) : row[col.accessor]) 
              : row[col.key];
            return new TableCell({
              children: [new Paragraph({
                children: [new TextRun({ text: formatValue(value, col.type) })],
                alignment: col.type === 'currency' || col.type === 'number' ? AlignmentType.RIGHT : AlignmentType.LEFT
              })]
            });
          })
        })
      )
    ];
    
    // Créer le document
    const doc = new Document({
      sections: [{
        properties: {},
        children: [
          // Titre
          new Paragraph({
            children: [new TextRun({ text: title, bold: true, size: 36, color: '0284c7' })],
            heading: HeadingLevel.HEADING_1,
            alignment: AlignmentType.CENTER,
            spacing: { after: 200 }
          }),
          // Sous-titre
          ...(subtitle ? [new Paragraph({
            children: [new TextRun({ text: subtitle, size: 24, color: '666666' })],
            alignment: AlignmentType.CENTER,
            spacing: { after: 200 }
          })] : []),
          // Date
          new Paragraph({
            children: [new TextRun({ 
              text: `Généré le: ${new Date().toLocaleString('fr-FR')}`, 
              size: 20, 
              color: '999999',
              italics: true 
            })],
            alignment: AlignmentType.CENTER,
            spacing: { after: 400 }
          }),
          // Description
          ...(description ? [new Paragraph({
            children: [new TextRun({ text: description })],
            spacing: { after: 300 }
          })] : []),
          // Tableau
          new Table({
            width: { size: 100, type: WidthType.PERCENTAGE },
            rows: tableRows,
            borders: {
              top: { style: BorderStyle.SINGLE, size: 1, color: 'CCCCCC' },
              bottom: { style: BorderStyle.SINGLE, size: 1, color: 'CCCCCC' },
              left: { style: BorderStyle.SINGLE, size: 1, color: 'CCCCCC' },
              right: { style: BorderStyle.SINGLE, size: 1, color: 'CCCCCC' },
              insideHorizontal: { style: BorderStyle.SINGLE, size: 1, color: 'CCCCCC' },
              insideVertical: { style: BorderStyle.SINGLE, size: 1, color: 'CCCCCC' }
            }
          }),
          // Footer
          new Paragraph({
            children: [new TextRun({ 
              text: `\n\n${DEFAULT_CONFIG.companyName} - Document généré automatiquement`, 
              size: 18, 
              color: '999999' 
            })],
            alignment: AlignmentType.CENTER,
            spacing: { before: 400 }
          })
        ]
      }]
    });
    
    // Générer et télécharger
    const blob = await Packer.toBlob(doc);
    saveAs(blob, `${filename}.docx`);
    
    return true;
  } catch (error) {
    console.error('Erreur export Word:', error);
    throw error;
  }
};

/**
 * Export universel - choisir le format
 */
export const exportData = async (format, data, columns, filename, options = {}) => {
  switch (format.toLowerCase()) {
    case 'excel':
    case 'xlsx':
      return exportToExcel(data, columns, filename, options);
    case 'pdf':
      return exportToPDF(data, columns, filename, options);
    case 'word':
    case 'docx':
      return await exportToWord(data, columns, filename, options);
    default:
      throw new Error(`Format d'export non supporté: ${format}`);
  }
};

/**
 * Export rapide pour les rapports
 */
export const exportReport = async (reportType, data, options = {}) => {
  const { format = 'pdf', ...rest } = options;
  
  // Configurations prédéfinies par type de rapport
  const reportConfigs = {
    sales: {
      title: 'Rapport des Ventes',
      columns: [
        { key: 'reference', header: 'Référence' },
        { key: 'date', header: 'Date', type: 'date' },
        { key: 'customer', header: 'Client' },
        { key: 'total', header: 'Total', type: 'currency' },
        { key: 'status', header: 'Statut' }
      ]
    },
    purchases: {
      title: 'Rapport des Achats',
      columns: [
        { key: 'reference', header: 'Référence' },
        { key: 'date', header: 'Date', type: 'date' },
        { key: 'supplier', header: 'Fournisseur' },
        { key: 'total', header: 'Total', type: 'currency' },
        { key: 'status', header: 'Statut' }
      ]
    },
    inventory: {
      title: 'État du Stock',
      columns: [
        { key: 'product', header: 'Produit' },
        { key: 'sku', header: 'SKU' },
        { key: 'quantity', header: 'Quantité', type: 'number' },
        { key: 'unit_price', header: 'Prix Unitaire', type: 'currency' },
        { key: 'total_value', header: 'Valeur Totale', type: 'currency' }
      ]
    },
    cash: {
      title: 'Rapport de Caisse',
      columns: [
        { key: 'date', header: 'Date', type: 'datetime' },
        { key: 'type', header: 'Type' },
        { key: 'description', header: 'Description' },
        { key: 'amount', header: 'Montant', type: 'currency' },
        { key: 'balance', header: 'Solde', type: 'currency' }
      ]
    },
    accounting: {
      title: 'Rapport Comptable',
      columns: [
        { key: 'account', header: 'Compte' },
        { key: 'label', header: 'Libellé' },
        { key: 'debit', header: 'Débit', type: 'currency' },
        { key: 'credit', header: 'Crédit', type: 'currency' },
        { key: 'balance', header: 'Solde', type: 'currency' }
      ]
    }
  };
  
  const config = reportConfigs[reportType] || { title: 'Rapport', columns: [] };
  
  return exportData(
    format, 
    data, 
    rest.columns || config.columns, 
    rest.filename || reportType,
    { title: config.title, ...rest }
  );
};

export default {
  exportToExcel,
  exportToPDF,
  exportToWord,
  exportData,
  exportReport
};
