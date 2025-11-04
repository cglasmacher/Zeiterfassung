import React from "react";
import { useDroppable } from "@dnd-kit/core";
import { Box } from "@mui/material";

export default function DroppableCell({ employee, date, children }) {
  const { setNodeRef, isOver } = useDroppable({
    id: `cell-${employee.id}-${date}`,
    data: { employee, date },
  });

  return (
    <Box
      ref={setNodeRef}
      sx={{
        border: isOver ? "2px dashed #1976d2" : "1px solid #e0e0e0",
        minHeight: 64,
        borderRadius: 1,
        p: 0.5,
        bgcolor: isOver ? "action.hover" : "transparent",
        transition: "0.1s",
      }}
    >
      {children}
    </Box>
  );
}
