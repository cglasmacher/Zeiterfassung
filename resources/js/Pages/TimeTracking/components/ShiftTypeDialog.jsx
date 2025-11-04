import React, { useState } from "react";
import {
  IconButton,
  Dialog,
  DialogTitle,
  DialogContent,
  MenuItem,
  Select,
  Button,
  Box,
  Tooltip,
} from "@mui/material";
import { Add } from "@mui/icons-material";

export default function ShiftTypeDialog({ types, onSelect }) {
  const [open, setOpen] = useState(false);
  const [selected, setSelected] = useState("");

  const handleConfirm = () => {
    if (selected) {
      onSelect(selected);
      setSelected("");
      setOpen(false);
    }
  };

  return (
    <>
      <Tooltip title="Schicht hinzufügen">
        <IconButton size="small" color="primary" onClick={() => setOpen(true)}>
          <Add fontSize="small" />
        </IconButton>
      </Tooltip>

      <Dialog open={open} onClose={() => setOpen(false)}>
        <DialogTitle>Schicht auswählen</DialogTitle>
        <DialogContent>
          <Box sx={{ p: 1 }}>
            <Select
              fullWidth
              value={selected}
              onChange={(e) => setSelected(e.target.value)}
              displayEmpty
            >
              <MenuItem value="">– Bitte wählen –</MenuItem>
              {types.map((t) => (
                <MenuItem key={t.id} value={t.id}>
                  {t.name} ({t.default_start.slice(0, 5)}–{t.default_end.slice(0, 5)})
                </MenuItem>
              ))}
            </Select>
            <Box mt={2} display="flex" justifyContent="flex-end" gap={1}>
              <Button onClick={() => setOpen(false)}>Abbrechen</Button>
              <Button variant="contained" onClick={handleConfirm}>
                Hinzufügen
              </Button>
            </Box>
          </Box>
        </DialogContent>
      </Dialog>
    </>
  );
}
