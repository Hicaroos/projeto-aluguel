/**
 * Shrink an image in the browser before uploading it, re-encoding it as JPEG.
 *
 * Phone photos of several megabytes become a few hundred kilobytes, and re-encoding also
 * drops their metadata (such as the GPS location). Images already smaller than `maxSize`
 * keep their dimensions.
 */
export async function resizeImage(
    file: File,
    maxSize: number,
    quality = 0.82,
): Promise<File> {
    const bitmap = await createImageBitmap(file);
    const scale = Math.min(1, maxSize / Math.max(bitmap.width, bitmap.height));
    const width = Math.round(bitmap.width * scale);
    const height = Math.round(bitmap.height * scale);

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;

    const context = canvas.getContext('2d');

    if (!context) {
        bitmap.close();

        throw new Error('Canvas is not supported.');
    }

    // JPEG has no transparency: paint transparent PNG areas white instead of black.
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, width, height);
    context.drawImage(bitmap, 0, 0, width, height);
    bitmap.close();

    const blob = await new Promise<Blob | null>((resolve) =>
        canvas.toBlob(resolve, 'image/jpeg', quality),
    );

    if (!blob) {
        throw new Error('The image could not be encoded.');
    }

    const name = file.name.replace(/\.[^.]+$/, '') || 'foto';

    return new File([blob], `${name}.jpg`, { type: 'image/jpeg' });
}
