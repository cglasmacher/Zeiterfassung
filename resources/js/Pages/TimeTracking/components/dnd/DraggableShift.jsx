import React from "react";
import { useDraggable } from "@dnd-kit/core";
import ShiftCardMUI from "../ShiftCardMUI";

export default function DraggableShift({ shift, onDelete }) {
  const { attributes, listeners, setNodeRef, transform, isDragging } =
    useDraggable({
      id: `shift-${shift.id}`,
      data: { shift },
    });

  const style = {
    transform: transform
      ? `translate3d(${transform.x}px, ${transform.y}px, 0)`
      : undefined,
    opacity: isDragging ? 0.6 : 1,
    cursor: "grab",
  };

  return (
    <div ref={setNodeRef} style={style} {...listeners} {...attributes}>
      <ShiftCardMUI shift={shift} onDelete={onDelete} />
    </div>
  );
}
