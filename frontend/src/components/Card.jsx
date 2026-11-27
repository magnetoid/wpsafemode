export function Card({ title, aside, children }) {
  return (
    <section class="rounded-card border border-edge bg-surface p-6 shadow-card">
      {(title || aside) && (
        <div class="mb-5 flex items-center justify-between gap-4">
          {title && <h2 class="font-display text-lg font-semibold">{title}</h2>}
          {aside}
        </div>
      )}
      {children}
    </section>
  )
}
