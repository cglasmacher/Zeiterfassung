import React from "react";
import {
  Card,
  CardContent,
  IconButton,
  Typography,
  Tooltip,
} from "@mui/material";
import { Delete } from "@mui/icons-material";

export default function ShiftCardMUI({ shift, onDelete }) {
  return (
    <Card
      variant="outlined"
      sx={{
        bgcolor: "primary.light",
        color: "white",
        position: "relative",
        "&:hover": { boxShadow: 3 },
      }}
    >
      <CardContent sx={{ p: 1 }}>
        <Typography variant="subtitle2" fontWeight={600}>
          {shift.shift_type.name}
        </Typography>
        <Typography variant="body2">
          {shift.start_time.slice(0, 5)} – {shift.end_time.slice(0, 5)}
        </Typography>
      </CardContent>
      <Tooltip title="Schicht löschen">
        <IconButton
          size="small"
          sx={{ position: "absolute", top: 0, right: 0, color: "white" }}
          onClick={onDelete}
        >
          <Delete fontSize="inherit" />
        </IconButton>
      </Tooltip>
    </Card>
  );
}
