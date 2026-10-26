// Synthesized game sound cues.
import { mkdirSync, writeFileSync } from 'node:fs';
mkdirSync('public/sounds', { recursive: true });
for (const [name, notes] of Object.entries({ pick:[440,660], flip:[180,360,540], reveal:[523,659,784], win:[523,659,784,1047], draw:[440,440,523] })) {
    const rate=22050, duration=notes.length*.12, count=Math.floor(rate*duration), out=Buffer.alloc(44+count*2);
    out.write('RIFF'); out.writeUInt32LE(36+count*2,4); out.write('WAVE',8); out.write('fmt ',12); out.writeUInt32LE(16,16); out.writeUInt16LE(1,20); out.writeUInt16LE(1,22); out.writeUInt32LE(rate,24); out.writeUInt32LE(rate*2,28); out.writeUInt16LE(2,32); out.writeUInt16LE(16,34); out.write('data',36); out.writeUInt32LE(count*2,40);
    for(let n=0;n<count;n++){ const t=n/rate, local=t%.12, freq=notes[Math.min(notes.length-1,Math.floor(t/.12))]; const envelope=Math.sin(Math.PI*local/.12)**2; out.writeInt16LE(Math.round(Math.sin(2*Math.PI*freq*t)*envelope*6500),44+n*2); }
    writeFileSync(`public/sounds/${name}.wav`,out);
}
