import React, { useState, useEffect } from "react";
import {
  Box,
  Typography,
  Button,
  Paper,
  Grid,
  CircularProgress,
} from "@mui/material";
import { ArrowBack, ArrowForward } from "@mui/icons-material";
import axios from "axios";
import dayjs from "dayjs";
import ShiftGridMUI from "./components/ShiftGridMUI";
import WeekNavigation from "./components/WeekNavigation";

export default function ShiftPlanner() {
  const [weekStart, setWeekStart] = useState(dayjs().startOf("week"));
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  const loadData = async () => {
    setLoading(true);
    const res = await axios.get("/api/shifts", {
      params: {
        start: weekStart.format("YYYY-MM-DD"),
        end: weekStart.add(6, "day").format("YYYY-MM-DD"),
      },
    });
    setData(res.data);
    setLoading(false);
  };

  useEffect(() => {
    loadData();
  }, [weekStart]);

  return (
    <Box sx={{ p: 3 }}>
      <Typography variant="h4" fontWeight={700} mb={3}>
        Dienstplan
      </Typography>

      <WeekNavigation weekStart={weekStart} setWeekStart={setWeekStart} />

      <Paper sx={{ p: 2, mt: 2 }}>
        {loading ? (
          <Box textAlign="center" py={5}>
            <CircularProgress />
          </Box>
        ) : (
          <ShiftGridMUI {...data} reload={loadData} />
        )}
      </Paper>
    </Box>
  );
}
