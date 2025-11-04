import React from "react";
import { Box, Button, Typography } from "@mui/material";
import { ArrowBack, ArrowForward } from "@mui/icons-material";
import dayjs from "dayjs";

export default function WeekNavigation({ weekStart, setWeekStart }) {
  return (
    <Box
      display="flex"
      justifyContent="space-between"
      alignItems="center"
      mb={2}
    >
      <Button
        startIcon={<ArrowBack />}
        onClick={() => setWeekStart(weekStart.subtract(7, "day"))}
      >
        Vorherige Woche
      </Button>
      <Typography variant="h6">
        {weekStart.format("DD.MM")} – {weekStart.add(6, "day").format("DD.MM.YYYY")}
      </Typography>
      <Button
        endIcon={<ArrowForward />}
        onClick={() => setWeekStart(weekStart.add(7, "day"))}
      >
        Nächste Woche
      </Button>
    </Box>
  );
}
