import { readFileSync } from 'node:fs';
import { join } from 'node:path';

const CRC_TABLE = Array.from({ length: 256 }, (_, n) => {
    let c = n;
    for (let k = 0; k < 8; k++) {
        c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    }

    return c >>> 0;
});

function crc32(data: Buffer): number {
    let crc = 0xffffffff;
    for (const byte of data) {
        crc = CRC_TABLE[(crc ^ byte) & 0xff] ^ (crc >>> 8);
    }

    return (crc ^ 0xffffffff) >>> 0;
}

/** A ZIP archive with stored (uncompressed) entries: enough for upload tests. */
export function zip(entries: Record<string, Buffer>): Buffer {
    const locals: Buffer[] = [];
    const centrals: Buffer[] = [];
    let offset = 0;

    for (const [name, data] of Object.entries(entries)) {
        const fileName = Buffer.from(name, 'utf8');
        const crc = crc32(data);

        const local = Buffer.alloc(30);
        local.writeUInt32LE(0x04034b50, 0);
        local.writeUInt16LE(20, 4); // version needed
        local.writeUInt16LE(0x0800, 6); // UTF-8 names
        local.writeUInt32LE(crc, 14);
        local.writeUInt32LE(data.length, 18);
        local.writeUInt32LE(data.length, 22);
        local.writeUInt16LE(fileName.length, 26);
        locals.push(local, fileName, data);

        const central = Buffer.alloc(46);
        central.writeUInt32LE(0x02014b50, 0);
        central.writeUInt16LE(20, 4); // version made by
        central.writeUInt16LE(20, 6);
        central.writeUInt16LE(0x0800, 8);
        central.writeUInt32LE(crc, 16);
        central.writeUInt32LE(data.length, 20);
        central.writeUInt32LE(data.length, 24);
        central.writeUInt16LE(fileName.length, 28);
        central.writeUInt32LE(offset, 42);
        centrals.push(central, fileName);

        offset += local.length + fileName.length + data.length;
    }

    const directory = Buffer.concat(centrals);
    const end = Buffer.alloc(22);
    end.writeUInt32LE(0x06054b50, 0);
    end.writeUInt16LE(centrals.length / 2, 8);
    end.writeUInt16LE(centrals.length / 2, 10);
    end.writeUInt32LE(directory.length, 12);
    end.writeUInt32LE(offset, 16);

    return Buffer.concat([...locals, directory, end]);
}

/** The LinkedIn data export used by the Pest suite (tests/Fixtures/linkedin). */
export function linkedinExport(): Buffer {
    const dir = join(import.meta.dirname, '../../Fixtures/linkedin');
    const files = [
        'Profile.csv',
        'Positions.csv',
        'Education.csv',
        'Skills.csv',
        'Languages.csv',
        'Certifications.csv',
        'Projects.csv',
        // Outside the allow-list: must be ignored.
        'messages.csv',
        'Connections.csv',
    ];

    return zip(
        Object.fromEntries(
            files.map((file) => [file, readFileSync(join(dir, file))]),
        ),
    );
}
