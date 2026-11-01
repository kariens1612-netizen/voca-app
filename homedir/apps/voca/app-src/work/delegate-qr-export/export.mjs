import fs from 'node:fs/promises';
import QRCode from 'qrcode';
import { PNG } from 'pngjs';
import { BinaryBitmap, HybridBinarizer, RGBLuminanceSource, QRCodeReader, DecodeHintType } from '@zxing/library';

const directory = new URL('./', import.meta.url);
const delegates = JSON.parse(await fs.readFile(new URL('delegates.json', directory), 'utf8'));
const output = new URL('../../outputs/VOCA-Delegate-QR-Codes/', directory);
await fs.mkdir(new URL('PNG/', output), { recursive: true });
await fs.mkdir(new URL('SVG/', output), { recursive: true });
const index = [['Delegate', 'Filename', 'QR reference']];
let sequence = 0;
for (const delegate of delegates) {
    sequence++;
    const filename = `${String(sequence).padStart(2, '0')}-${delegate.name.normalize('NFKD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-zA-Z0-9]+/g, '-').replace(/^-|-$/g, '')}`;
    const options = { errorCorrectionLevel: 'H', margin: 4, width: 1200, color: { dark: '#000000', light: '#ffffff' } };
    const png = await QRCode.toBuffer(delegate.reference, options);
    await fs.writeFile(new URL(`PNG/${filename}.png`, output), png);
    await fs.writeFile(new URL(`SVG/${filename}.svg`, output), await QRCode.toString(delegate.reference, { ...options, type: 'svg' }));
    const image = PNG.sync.read(png);
    const luminance = new Uint8ClampedArray(image.width * image.height);
    for (let pixel = 0; pixel < luminance.length; pixel++) luminance[pixel] = image.data[pixel * 4];
    const decoded = new QRCodeReader().decode(new BinaryBitmap(new HybridBinarizer(new RGBLuminanceSource(luminance, image.width, image.height))), new Map([[DecodeHintType.PURE_BARCODE, true]]));
    if (decoded.getText() !== delegate.reference) throw new Error(`QR verification failed: ${filename}`);
    index.push([delegate.name, filename, delegate.reference]);
}
const quote = value => `"${String(value).replaceAll('"', '""')}"`;
await fs.writeFile(new URL('Delegate-Index.csv', output), '\uFEFF' + index.map(row => row.map(quote).join(',')).join('\r\n'));
console.log(`${delegates.length} PNG codes and ${delegates.length} SVG codes created; all PNG codes independently decoded and verified.`);
