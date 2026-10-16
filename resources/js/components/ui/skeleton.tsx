import { cn } from "@/lib/utils"

function Skeleton({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="skeleton"
      className={cn("placeholder min-h-0 animate-pulse [animation-duration:4s] motion-reduce:animate-none", className)}
      {...props}
    />
  )
}

export { Skeleton }
