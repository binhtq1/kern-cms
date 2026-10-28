export type GalleryImage = { img: string; description: string };
export function reorderGallery(
    images: GalleryImage[],
    from: number,
    to: number,
) {
    if (
        from < 0 ||
        to < 0 ||
        from >= images.length ||
        to >= images.length ||
        from === to
    )
        return images;
    const next = [...images];
    next.splice(to, 0, next.splice(from, 1)[0]);
    return next;
}
